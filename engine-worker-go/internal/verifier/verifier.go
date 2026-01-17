package verifier

import "context"

const (
    CategoryValid   = "valid"
    CategoryInvalid = "invalid"
    CategoryRisky   = "risky"
)

type Result struct {
    Category string
    Reason   string
}

type Verifier interface {
    Verify(ctx context.Context, email string) Result
}

type Config struct {
    DNSTimeout             int
    SMTPConnectTimeout     int
    SMTPReadTimeout        int
    SMTPEhloTimeout        int
    MaxMXAttempts          int
    RetryableNetworkRetries int
    BackoffBaseMs          int
    HeloName               string
    PerDomainConcurrency   int
    SMTPRateLimitPerMinute int
}
