package verifier

import (
	"bufio"
	"context"
	"errors"
	"fmt"
	"net"
	"strconv"
	"strings"
	"time"
)

type SMTPChecker interface {
	Check(ctx context.Context, host string) Result
}

type SMTPDialer interface {
	DialContext(ctx context.Context, network, address string) (net.Conn, error)
}

type NetSMTPChecker struct {
	Dialer         SMTPDialer
	ConnectTimeout time.Duration
	ReadTimeout    time.Duration
	EhloTimeout    time.Duration
	HeloName       string
	RateLimiter    *RateLimiter
}

func (c NetSMTPChecker) Check(ctx context.Context, host string) Result {
	if c.HeloName == "" {
		c.HeloName = host
	}

	if err := c.waitRate(ctx); err != nil {
		return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
	}

	dialer := c.Dialer
	if dialer == nil {
		dialer = &net.Dialer{Timeout: c.ConnectTimeout}
	}

	connectCtx, cancel := context.WithTimeout(ctx, c.ConnectTimeout)
	defer cancel()

	conn, err := dialer.DialContext(connectCtx, "tcp", net.JoinHostPort(host, "25"))
	if err != nil {
		if isTimeout(err) || errors.Is(connectCtx.Err(), context.DeadlineExceeded) {
			return Result{Category: CategoryRisky, Reason: "smtp_connect_timeout"}
		}

		return Result{Category: CategoryRisky, Reason: "smtp_connect_timeout"}
	}
	defer conn.Close()

	if code, res := readSMTPResponse(conn, c.ReadTimeout); res != nil {
		return *res
	} else if code >= 500 {
		return Result{Category: CategoryInvalid, Reason: "smtp_unavailable"}
	} else if code >= 400 {
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if err := writeSMTP(conn, fmt.Sprintf("EHLO %s", c.HeloName), c.EhloTimeout); err != nil {
		if isTimeout(err) {
			return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
		}
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if code, res := readSMTPResponse(conn, c.EhloTimeout); res != nil {
		return *res
	} else if code >= 500 {
		return Result{Category: CategoryInvalid, Reason: "smtp_unavailable"}
	} else if code >= 400 {
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	_ = writeSMTP(conn, "QUIT", c.ReadTimeout)

	return Result{Category: CategoryValid, Reason: "smtp_connect_ok"}
}

func (c NetSMTPChecker) waitRate(ctx context.Context) error {
	if c.RateLimiter == nil {
		return nil
	}

	return c.RateLimiter.Wait(ctx)
}

func readSMTPResponse(conn net.Conn, timeout time.Duration) (int, *Result) {
	_ = conn.SetReadDeadline(time.Now().Add(timeout))
	reader := bufio.NewReader(conn)

	line, err := reader.ReadString('\n')
	if err != nil {
		if isTimeout(err) {
			result := Result{Category: CategoryRisky, Reason: "smtp_timeout"}
			return 0, &result
		}
		result := Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
		return 0, &result
	}

	line = strings.TrimSpace(line)
	code := parseSMTPCode(line)

	if len(line) >= 4 && line[3] == '-' {
		for {
			next, err := reader.ReadString('\n')
			if err != nil {
				break
			}
			next = strings.TrimSpace(next)
			if len(next) >= 4 && next[3] == ' ' {
				break
			}
		}
	}

	return code, nil
}

func writeSMTP(conn net.Conn, command string, timeout time.Duration) error {
	_ = conn.SetWriteDeadline(time.Now().Add(timeout))
	_, err := fmt.Fprintf(conn, "%s\r\n", command)
	return err
}

func parseSMTPCode(line string) int {
	if len(line) < 3 {
		return 0
	}
	code, err := strconv.Atoi(line[0:3])
	if err != nil {
		return 0
	}
	return code
}

func isTimeout(err error) bool {
	var netErr net.Error
	if errors.As(err, &netErr) {
		return netErr.Timeout()
	}
	return false
}
