package verifier

import (
	"context"
	"crypto/rand"
	"errors"
	"fmt"
	"net"
	"strconv"
	"strings"
	"time"
)

type SMTPChecker interface {
	Check(ctx context.Context, host, email string) Result
}

type SMTPDialer interface {
	DialContext(ctx context.Context, network, address string) (net.Conn, error)
}

type NetSMTPChecker struct {
	Dialer          SMTPDialer
	ConnectTimeout  time.Duration
	ReadTimeout     time.Duration
	EhloTimeout     time.Duration
	HeloName        string
	ProviderProfile string
	RateLimiter     *RateLimiter
}

func (c NetSMTPChecker) Check(ctx context.Context, host, email string) Result {
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

	if reply, res := readSMTPReply(conn, c.ReadTimeout); res != nil {
		return *res
	} else if result, stop := classifySMTPSessionReply("banner", reply, c.ProviderProfile, host); stop {
		return result
	}

	if err := writeSMTP(conn, fmt.Sprintf("EHLO %s", c.HeloName), c.EhloTimeout); err != nil {
		if isTimeout(err) {
			return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
		}
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if reply, res := readSMTPReply(conn, c.EhloTimeout); res != nil {
		return *res
	} else if result, stop := classifySMTPSessionReply("ehlo", reply, c.ProviderProfile, host); stop {
		return result
	}

	_ = writeSMTP(conn, "QUIT", c.ReadTimeout)

	return Result{
		Category:        CategoryValid,
		Reason:          "smtp_connect_ok",
		ReasonCode:      "smtp_connect_ok",
		DecisionClass:   DecisionDeliverable,
		ProviderProfile: detectSMTPProviderProfile(c.ProviderProfile, host, ""),
	}
}

type NetSMTPProber struct {
	Dialer                   SMTPDialer
	ConnectTimeout           time.Duration
	ReadTimeout              time.Duration
	EhloTimeout              time.Duration
	HeloName                 string
	ProviderProfile          string
	MailFromAddress          string
	RateLimiter              *RateLimiter
	CatchAllDetectionEnabled bool
	RandomLocalPart          func() string
}

func (p NetSMTPProber) Check(ctx context.Context, host, email string) Result {
	if email == "" {
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}
	if p.HeloName == "" {
		p.HeloName = host
	}
	if p.MailFromAddress == "" {
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if err := p.waitRate(ctx); err != nil {
		return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
	}

	dialer := p.Dialer
	if dialer == nil {
		dialer = &net.Dialer{Timeout: p.ConnectTimeout}
	}

	connectCtx, cancel := context.WithTimeout(ctx, p.ConnectTimeout)
	defer cancel()

	conn, err := dialer.DialContext(connectCtx, "tcp", net.JoinHostPort(host, "25"))
	if err != nil {
		if isTimeout(err) || errors.Is(connectCtx.Err(), context.DeadlineExceeded) {
			return Result{Category: CategoryRisky, Reason: "smtp_connect_timeout"}
		}

		return Result{Category: CategoryRisky, Reason: "smtp_connect_timeout"}
	}
	defer conn.Close()

	if reply, res := readSMTPReply(conn, p.ReadTimeout); res != nil {
		return *res
	} else if result, stop := classifySMTPSessionReply("banner", reply, p.ProviderProfile, host); stop {
		return result
	}

	if result := p.sayHello(conn, host); result.Category != "" {
		return result
	}

	if err := writeSMTP(conn, fmt.Sprintf("MAIL FROM:<%s>", p.MailFromAddress), p.ReadTimeout); err != nil {
		if isTimeout(err) {
			return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
		}
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if reply, res := readSMTPReply(conn, p.ReadTimeout); res != nil {
		return *res
	} else if result, stop := classifySMTPSessionReply("mail_from", reply, p.ProviderProfile, host); stop {
		return result
	}

	rcptResult := p.checkRcpt(conn, host, email, true)
	if rcptResult.Category != CategoryValid {
		_ = writeSMTP(conn, "QUIT", p.ReadTimeout)
		return rcptResult
	}

	if p.CatchAllDetectionEnabled {
		catchAllResult := p.checkCatchAll(conn, host, email)
		_ = writeSMTP(conn, "QUIT", p.ReadTimeout)
		return catchAllResult
	}

	_ = writeSMTP(conn, "QUIT", p.ReadTimeout)
	return rcptResult
}

func (p NetSMTPProber) waitRate(ctx context.Context) error {
	if p.RateLimiter == nil {
		return nil
	}

	return p.RateLimiter.Wait(ctx)
}

func (p NetSMTPProber) sayHello(conn net.Conn, host string) Result {
	if err := writeSMTP(conn, fmt.Sprintf("EHLO %s", p.HeloName), p.EhloTimeout); err != nil {
		if isTimeout(err) {
			return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
		}
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	if reply, res := readSMTPReply(conn, p.EhloTimeout); res != nil {
		return *res
	} else if reply.Code >= 500 {
		if err := writeSMTP(conn, fmt.Sprintf("HELO %s", p.HeloName), p.EhloTimeout); err != nil {
			if isTimeout(err) {
				return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
			}
			return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
		}
		if heloReply, res := readSMTPReply(conn, p.EhloTimeout); res != nil {
			return *res
		} else if result, stop := classifySMTPSessionReply("helo", heloReply, p.ProviderProfile, host); stop {
			return result
		}
	} else if result, stop := classifySMTPSessionReply("ehlo", reply, p.ProviderProfile, host); stop {
		return result
	}

	return Result{}
}

func (p NetSMTPProber) checkRcpt(conn net.Conn, host, email string, allowValid bool) Result {
	if err := writeSMTP(conn, fmt.Sprintf("RCPT TO:<%s>", email), p.ReadTimeout); err != nil {
		if isTimeout(err) {
			return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
		}
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	reply, res := readSMTPReply(conn, p.ReadTimeout)
	if res != nil {
		return *res
	}

	return classifySMTPRcptReply(reply, p.ProviderProfile, host, allowValid)
}

func (p NetSMTPProber) checkCatchAll(conn net.Conn, host, email string) Result {
	domain := ""
	if at := strings.LastIndex(email, "@"); at != -1 && at+1 < len(email) {
		domain = email[at+1:]
	}
	if domain == "" {
		return Result{Category: CategoryRisky, Reason: "smtp_tempfail"}
	}

	randomLocal := p.randomLocalPart()
	randomEmail := fmt.Sprintf("%s@%s", randomLocal, domain)

	result := p.checkRcpt(conn, host, randomEmail, true)
	if result.Category == CategoryValid {
		return Result{Category: CategoryRisky, Reason: "catch_all"}
	}

	if result.Category == CategoryInvalid {
		return Result{Category: CategoryValid, Reason: "rcpt_ok"}
	}

	return result
}

func (p NetSMTPProber) randomLocalPart() string {
	if p.RandomLocalPart != nil {
		return p.RandomLocalPart()
	}

	const letters = "abcdefghijklmnopqrstuvwxyz0123456789"
	const length = 12
	bytes := make([]byte, length)
	if _, err := rand.Read(bytes); err != nil {
		return fmt.Sprintf("probe%d", time.Now().UnixNano())
	}

	for i := range bytes {
		bytes[i] = letters[int(bytes[i])%len(letters)]
	}

	return string(bytes)
}

func (c NetSMTPChecker) waitRate(ctx context.Context) error {
	if c.RateLimiter == nil {
		return nil
	}

	return c.RateLimiter.Wait(ctx)
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
