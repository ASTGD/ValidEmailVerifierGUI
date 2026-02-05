package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/go-chi/chi/v5"
)

type Server struct {
	store *Store
	cfg   Config
}

func NewServer(store *Store, cfg Config) *Server {
	return &Server{store: store, cfg: cfg}
}

func (s *Server) Router() http.Handler {
	r := chi.NewRouter()
	r.Use(s.authMiddleware)

	r.Post("/api/workers/heartbeat", s.handleHeartbeat)
	r.Get("/api/workers", s.handleWorkers)
	r.Post("/api/workers/{workerID}/pause", s.handleSetDesired("paused"))
	r.Post("/api/workers/{workerID}/resume", s.handleSetDesired("running"))
	r.Post("/api/workers/{workerID}/drain", s.handleSetDesired("draining"))
	r.Post("/api/workers/{workerID}/stop", s.handleSetDesired("stopped"))

	r.Get("/api/pools", s.handlePools)
	r.Post("/api/pools/{pool}/scale", s.handleScalePool)

	r.Get("/health", func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	return r
}

func (s *Server) ListenAndServe() error {
	addr := ":" + s.cfg.Port
	server := &http.Server{
		Addr:              addr,
		Handler:           s.Router(),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      10 * time.Second,
		IdleTimeout:       30 * time.Second,
	}

	shutdown := make(chan os.Signal, 1)
	signal.Notify(shutdown, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		<-shutdown
		ctx, cancel := context.WithTimeout(context.Background(), time.Duration(s.cfg.ShutdownTimeoutSec)*time.Second)
		defer cancel()
		if err := server.Shutdown(ctx); err != nil {
			log.Printf("shutdown error: %v", err)
		}
	}()

	log.Printf("control plane listening on %s", addr)
	return server.ListenAndServe()
}
