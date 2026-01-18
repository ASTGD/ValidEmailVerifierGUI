package verifier

import (
	"context"
	"errors"
	"net"
	"strings"
)

type MXResolver interface {
	LookupMX(ctx context.Context, domain string) ([]*net.MX, error)
}

type NetMXResolver struct {
	Resolver *net.Resolver
}

func (r NetMXResolver) LookupMX(ctx context.Context, domain string) ([]*net.MX, error) {
	resolver := r.Resolver
	if resolver == nil {
		resolver = net.DefaultResolver
	}

	return resolver.LookupMX(ctx, domain)
}

func classifyDNSError(err error) Result {
	if err == nil {
		return Result{}
	}

	var netErr net.Error
	if errors.As(err, &netErr) && netErr.Timeout() {
		return Result{Category: CategoryRisky, Reason: "dns_timeout"}
	}

	message := strings.ToLower(err.Error())
	if strings.Contains(message, "servfail") {
		return Result{Category: CategoryRisky, Reason: "dns_servfail"}
	}

	return Result{Category: CategoryRisky, Reason: "dns_servfail"}
}
