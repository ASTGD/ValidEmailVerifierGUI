package verifier

import (
	"context"
	"sync"
	"time"
)

type DomainLimiter struct {
	mu         sync.Mutex
	semaphores map[string]chan struct{}
	perDomain  int
}

func NewDomainLimiter(perDomain int) *DomainLimiter {
	return &DomainLimiter{
		semaphores: make(map[string]chan struct{}),
		perDomain:  perDomain,
	}
}

func (d *DomainLimiter) Acquire(ctx context.Context, domain string) (func(), error) {
	if d == nil || d.perDomain <= 0 {
		return func() {}, nil
	}

	d.mu.Lock()
	sem, ok := d.semaphores[domain]
	if !ok {
		sem = make(chan struct{}, d.perDomain)
		d.semaphores[domain] = sem
	}
	d.mu.Unlock()

	select {
	case sem <- struct{}{}:
		return func() { <-sem }, nil
	case <-ctx.Done():
		return nil, ctx.Err()
	}
}

type RateLimiter struct {
	tick *time.Ticker
}

func NewRateLimiter(perMinute int) *RateLimiter {
	if perMinute <= 0 {
		return nil
	}

	interval := time.Minute / time.Duration(perMinute)
	return &RateLimiter{tick: time.NewTicker(interval)}
}

func (r *RateLimiter) Wait(ctx context.Context) error {
	if r == nil {
		return nil
	}

	select {
	case <-r.tick.C:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func (r *RateLimiter) Stop() {
	if r == nil {
		return
	}
	r.tick.Stop()
}
