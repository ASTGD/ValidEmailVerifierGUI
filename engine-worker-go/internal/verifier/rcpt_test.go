package verifier

import (
	"bufio"
	"context"
	"fmt"
	"net"
	"strings"
	"testing"
	"time"
)

type pipeDialer struct {
	conn net.Conn
}

func (d pipeDialer) DialContext(ctx context.Context, network, address string) (net.Conn, error) {
	return d.conn, nil
}

func runSMTPServer(t *testing.T, conn net.Conn, handler func(line string) string) {
	t.Helper()

	defer conn.Close()

	writer := bufio.NewWriter(conn)
	if _, err := writer.WriteString("220 test.local ESMTP\r\n"); err != nil {
		return
	}
	_ = writer.Flush()

	reader := bufio.NewReader(conn)
	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			return
		}

		line = strings.TrimSpace(line)
		response := handler(line)
		if response == "" {
			response = "250 OK"
		}

		if _, err := writer.WriteString(response + "\r\n"); err != nil {
			return
		}
		_ = writer.Flush()

		if strings.HasPrefix(strings.ToUpper(line), "QUIT") {
			return
		}
	}
}

func newProber(t *testing.T, conn net.Conn) NetSMTPProber {
	t.Helper()

	return NetSMTPProber{
		Dialer:                   pipeDialer{conn: conn},
		ConnectTimeout:           time.Second,
		ReadTimeout:              time.Second,
		EhloTimeout:              time.Second,
		HeloName:                 "helo.test",
		MailFromAddress:          "probe@helo.test",
		CatchAllDetectionEnabled: false,
	}
}

func TestSMTPProberRcptOk(t *testing.T) {
	client, server := net.Pipe()

	go runSMTPServer(t, server, func(line string) string {
		switch {
		case strings.HasPrefix(line, "EHLO"):
			return "250 OK"
		case strings.HasPrefix(line, "MAIL FROM"):
			return "250 OK"
		case strings.HasPrefix(line, "RCPT TO"):
			return "250 OK"
		case strings.HasPrefix(line, "QUIT"):
			return "221 Bye"
		default:
			return "250 OK"
		}
	})

	prober := newProber(t, client)
	res := prober.Check(context.Background(), "mx.test", "user@test.com")

	if res.Category != CategoryValid || res.Reason != "rcpt_ok" {
		t.Fatalf("expected rcpt_ok valid, got %s/%s", res.Category, res.Reason)
	}
}

func TestSMTPProberRcptRejected(t *testing.T) {
	client, server := net.Pipe()

	go runSMTPServer(t, server, func(line string) string {
		switch {
		case strings.HasPrefix(line, "EHLO"):
			return "250 OK"
		case strings.HasPrefix(line, "MAIL FROM"):
			return "250 OK"
		case strings.HasPrefix(line, "RCPT TO"):
			return "550 No such user"
		case strings.HasPrefix(line, "QUIT"):
			return "221 Bye"
		default:
			return "250 OK"
		}
	})

	prober := newProber(t, client)
	res := prober.Check(context.Background(), "mx.test", "user@test.com")

	if res.Category != CategoryInvalid || res.Reason != "rcpt_rejected" {
		t.Fatalf("expected rcpt_rejected invalid, got %s/%s", res.Category, res.Reason)
	}
}

func TestSMTPProberCatchAll(t *testing.T) {
	client, server := net.Pipe()

	randomLocal := "random123"

	go runSMTPServer(t, server, func(line string) string {
		switch {
		case strings.HasPrefix(line, "EHLO"):
			return "250 OK"
		case strings.HasPrefix(line, "MAIL FROM"):
			return "250 OK"
		case strings.HasPrefix(line, fmt.Sprintf("RCPT TO:<user@test.com>")):
			return "250 OK"
		case strings.HasPrefix(line, fmt.Sprintf("RCPT TO:<%s@test.com>", randomLocal)):
			return "250 OK"
		case strings.HasPrefix(line, "QUIT"):
			return "221 Bye"
		default:
			return "250 OK"
		}
	})

	prober := newProber(t, client)
	prober.CatchAllDetectionEnabled = true
	prober.RandomLocalPart = func() string {
		return randomLocal
	}

	res := prober.Check(context.Background(), "mx.test", "user@test.com")

	if res.Category != CategoryRisky || res.Reason != "catch_all_high_confidence" {
		t.Fatalf("expected catch_all_high_confidence risky, got %s/%s", res.Category, res.Reason)
	}
	if res.DecisionConfidence != "high" {
		t.Fatalf("expected high confidence, got %q", res.DecisionConfidence)
	}
}
