package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/smtp"
	"strings"
)

type SlackNotifier struct {
	WebhookURL string
}

func NewSlackNotifier(webhookURL string) *SlackNotifier {
	if webhookURL == "" {
		return nil
	}
	return &SlackNotifier{WebhookURL: webhookURL}
}

func (s *SlackNotifier) Notify(_ context.Context, alert AlertEvent) error {
	payload := map[string]string{
		"text": fmt.Sprintf("*%s*\n%s", alertTitle(alert), alertSummary(alert)),
	}
	body, _ := json.Marshal(payload)

	resp, err := http.Post(s.WebhookURL, "application/json", bytes.NewReader(body))
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 300 {
		return fmt.Errorf("slack webhook failed: %s", resp.Status)
	}
	return nil
}

type EmailNotifier struct {
	Host     string
	Port     int
	Username string
	Password string
	From     string
	To       []string
}

func NewEmailNotifier(cfg Config) *EmailNotifier {
	if cfg.SMTPHost == "" || cfg.SMTPPort == 0 || cfg.SMTPFrom == "" || len(cfg.SMTPTo) == 0 {
		return nil
	}

	return &EmailNotifier{
		Host:     cfg.SMTPHost,
		Port:     cfg.SMTPPort,
		Username: cfg.SMTPUsername,
		Password: cfg.SMTPPassword,
		From:     cfg.SMTPFrom,
		To:       cfg.SMTPTo,
	}
}

func (e *EmailNotifier) Notify(_ context.Context, alert AlertEvent) error {
	addr := fmt.Sprintf("%s:%d", e.Host, e.Port)
	subject := alertTitle(alert)
	body := alertSummary(alert)

	message := strings.Builder{}
	message.WriteString("From: " + e.From + "\r\n")
	message.WriteString("To: " + strings.Join(e.To, ",") + "\r\n")
	message.WriteString("Subject: " + subject + "\r\n")
	message.WriteString("Content-Type: text/plain; charset=UTF-8\r\n\r\n")
	message.WriteString(body)

	var auth smtp.Auth
	if e.Username != "" {
		auth = smtp.PlainAuth("", e.Username, e.Password, e.Host)
	}

	return smtp.SendMail(addr, auth, e.From, e.To, []byte(message.String()))
}
