package worker

import (
	"bufio"
	"bytes"
	"context"
	"encoding/csv"
	"fmt"
	"io"
	"net/http"
	"strings"
	"sync"
	"time"

	"engine-worker-go/internal/api"
	"engine-worker-go/internal/verifier"
)

type Config struct {
	PollInterval      time.Duration
	HeartbeatInterval time.Duration
	LeaseSeconds      *int
	MaxConcurrency    int
	Server            api.EngineServerPayload
	WorkerID          string
	Verifier          verifier.Verifier
}

type Worker struct {
	client *api.Client
	cfg    Config
	sem    chan struct{}
	wg     sync.WaitGroup
}

type chunkOutputs struct {
	ValidData    []byte
	InvalidData  []byte
	RiskyData    []byte
	EmailCount   int
	ValidCount   int
	InvalidCount int
	RiskyCount   int
}

func New(client *api.Client, cfg Config) *Worker {
	max := cfg.MaxConcurrency
	if max < 1 {
		max = 1
	}

	return &Worker{
		client: client,
		cfg:    cfg,
		sem:    make(chan struct{}, max),
	}
}

func (w *Worker) Run(ctx context.Context) error {
	lastHeartbeat := time.Time{}

	for {
		select {
		case <-ctx.Done():
			w.wg.Wait()
			return ctx.Err()
		default:
		}

		if time.Since(lastHeartbeat) >= w.cfg.HeartbeatInterval {
			if err := w.client.Heartbeat(ctx, w.cfg.Server); err != nil {
				fmt.Printf("heartbeat error: %v\n", err)
			}
			lastHeartbeat = time.Now()
		}

		if len(w.sem) >= cap(w.sem) {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		claimReq := api.ClaimNextRequest{
			EngineServer: w.cfg.Server,
			WorkerID:     w.cfg.WorkerID,
			LeaseSeconds: w.cfg.LeaseSeconds,
		}

		claim, ok, err := w.client.ClaimNext(ctx, claimReq)
		if err != nil {
			fmt.Printf("claim-next error: %v\n", err)
			time.Sleep(w.cfg.PollInterval)
			continue
		}
		if !ok {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		w.sem <- struct{}{}
		w.wg.Add(1)
		go func(claim *api.ClaimNextResponse) {
			defer w.wg.Done()
			defer func() { <-w.sem }()

			if err := w.processChunk(ctx, claim); err != nil {
				fmt.Printf("chunk %s error: %v\n", claim.Data.ChunkID, err)
			}
		}(claim)
	}
}

func (w *Worker) processChunk(ctx context.Context, claim *api.ClaimNextResponse) error {
	chunkID := claim.Data.ChunkID

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_claimed",
		"message": "Chunk claimed by worker.",
		"context": map[string]interface{}{
			"worker_id": w.cfg.WorkerID,
			"chunk_no":  claim.Data.ChunkNo,
		},
	})

	details, err := w.client.ChunkDetails(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to load chunk details", err, true)
	}

	inputURL, err := w.client.InputURL(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to fetch input url", err, true)
	}

	reader, err := downloadStream(ctx, inputURL.Data.URL)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to download input", err, true)
	}
	defer reader.Close()

	outputs, err := buildOutputs(ctx, reader, w.cfg.Verifier)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to parse input", err, false)
	}

	outputURLs, err := w.client.OutputURLs(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to fetch output urls", err, true)
	}

	if err := uploadSigned(ctx, outputURLs.Data.Targets.Valid.URL, outputs.ValidData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload valid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Invalid.URL, outputs.InvalidData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload invalid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Risky.URL, outputs.RiskyData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload risky output", err, true)
	}

	completePayload := map[string]interface{}{
		"output_disk":   outputURLs.Data.Disk,
		"valid_key":     outputURLs.Data.Targets.Valid.Key,
		"invalid_key":   outputURLs.Data.Targets.Invalid.Key,
		"risky_key":     outputURLs.Data.Targets.Risky.Key,
		"email_count":   outputs.EmailCount,
		"valid_count":   outputs.ValidCount,
		"invalid_count": outputs.InvalidCount,
		"risky_count":   outputs.RiskyCount,
	}

	if err := w.client.CompleteChunk(ctx, chunkID, completePayload); err != nil {
		return w.failChunk(ctx, chunkID, "failed to complete chunk", err, true)
	}

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_completed",
		"message": "Chunk completed by worker.",
		"context": map[string]interface{}{
			"chunk_no":      details.Data.ChunkNo,
			"email_count":   outputs.EmailCount,
			"valid_count":   outputs.ValidCount,
			"invalid_count": outputs.InvalidCount,
			"risky_count":   outputs.RiskyCount,
		},
	})

	return nil
}

func (w *Worker) failChunk(ctx context.Context, chunkID, message string, err error, retryable bool) error {
	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "error",
		"event":   "chunk_error",
		"message": message,
		"context": map[string]interface{}{
			"error": err.Error(),
		},
	})

	_ = w.client.FailChunk(ctx, chunkID, map[string]interface{}{
		"error_message": message,
		"retryable":     retryable,
	})

	return err
}

func downloadStream(ctx context.Context, url string) (io.ReadCloser, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		defer resp.Body.Close()
		return nil, fmt.Errorf("download failed with status %d", resp.StatusCode)
	}

	return resp.Body, nil
}

func uploadSigned(ctx context.Context, url string, data []byte) error {
	req, err := http.NewRequestWithContext(ctx, http.MethodPut, url, bytes.NewReader(data))
	if err != nil {
		return err
	}

	req.Header.Set("Content-Type", "text/csv")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("upload failed with status %d", resp.StatusCode)
	}

	return nil
}

func buildOutputs(ctx context.Context, reader io.Reader, engineVerifier verifier.Verifier) (*chunkOutputs, error) {
	if engineVerifier == nil {
		return nil, fmt.Errorf("verifier not configured")
	}

	validBuf := &bytes.Buffer{}
	invalidBuf := &bytes.Buffer{}
	riskyBuf := &bytes.Buffer{}

	validWriter := csv.NewWriter(validBuf)
	invalidWriter := csv.NewWriter(invalidBuf)
	riskyWriter := csv.NewWriter(riskyBuf)

	header := []string{"email", "reason"}
	_ = validWriter.Write(header)
	_ = invalidWriter.Write(header)
	_ = riskyWriter.Write(header)

	scanner := bufio.NewScanner(reader)
	scanner.Buffer(make([]byte, 0, 64*1024), 1024*1024)

	output := &chunkOutputs{}

	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" {
			continue
		}

		if isHeaderLine(line) {
			continue
		}

		output.EmailCount++
		result := engineVerifier.Verify(ctx, line)

		switch result.Category {
		case verifier.CategoryInvalid:
			output.InvalidCount++
			_ = invalidWriter.Write([]string{line, result.Reason})
		case verifier.CategoryRisky:
			output.RiskyCount++
			_ = riskyWriter.Write([]string{line, result.Reason})
		case verifier.CategoryValid:
			output.ValidCount++
			_ = validWriter.Write([]string{line, result.Reason})
		default:
			output.RiskyCount++
			reason := result.Reason
			if reason == "" {
				reason = "unknown"
			}
			_ = riskyWriter.Write([]string{line, reason})
		}
	}

	if err := scanner.Err(); err != nil {
		return nil, err
	}

	validWriter.Flush()
	invalidWriter.Flush()
	riskyWriter.Flush()

	if err := firstError(validWriter.Error(), invalidWriter.Error(), riskyWriter.Error()); err != nil {
		return nil, err
	}

	output.ValidData = validBuf.Bytes()
	output.InvalidData = invalidBuf.Bytes()
	output.RiskyData = riskyBuf.Bytes()

	return output, nil
}

func isHeaderLine(line string) bool {
	lower := strings.ToLower(strings.TrimSpace(line))
	if lower == "email" {
		return true
	}
	if strings.HasPrefix(lower, "email,") {
		return true
	}
	if strings.HasPrefix(lower, "email;") {
		return true
	}

	return !strings.Contains(line, "@")
}

func firstError(errors ...error) error {
	for _, err := range errors {
		if err != nil {
			return err
		}
	}

	return nil
}
