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
	views *ViewRenderer
}

func NewServer(store *Store, cfg Config) *Server {
	renderer, err := NewViewRenderer()
	if err != nil {
		panic(err)
	}

	return &Server{store: store, cfg: cfg, views: renderer}
}

func (s *Server) Router() http.Handler {
	r := chi.NewRouter()

	r.Get("/health", func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	r.Group(func(router chi.Router) {
		router.Use(s.authMiddleware)

		router.Post("/api/workers/heartbeat", s.handleHeartbeat)
		router.Get("/api/workers", s.handleWorkers)
		router.Post("/api/workers/{workerID}/pause", s.handleSetDesired("paused"))
		router.Post("/api/workers/{workerID}/resume", s.handleSetDesired("running"))
		router.Post("/api/workers/{workerID}/drain", s.handleSetDesired("draining"))
		router.Post("/api/workers/{workerID}/stop", s.handleSetDesired("stopped"))

		router.Get("/api/pools", s.handlePools)
		router.Post("/api/pools/{pool}/scale", s.handleScalePool)

		router.Get("/ui", s.handleUIRedirect)
		router.Get("/ui/overview", s.handleUIRedirect)
		router.Get("/ui/workers", s.handleUIRedirect)
		router.Get("/ui/pools", s.handleUIRedirect)
		router.Post("/ui/workers/{workerID}/pause", s.handleUISetDesired("paused"))
		router.Post("/ui/workers/{workerID}/resume", s.handleUISetDesired("running"))
		router.Post("/ui/workers/{workerID}/drain", s.handleUISetDesired("draining"))
		router.Post("/ui/workers/{workerID}/stop", s.handleUISetDesired("stopped"))
		router.Post("/ui/pools/{pool}/scale", s.handleUIScalePool)

		router.Get("/verifier-engine-room/overview", s.handleUIOverview)
		router.Get("/verifier-engine-room/workers", s.handleUIWorkers)
		router.Get("/verifier-engine-room/pools", s.handleUIPools)
		router.Post("/verifier-engine-room/workers/{workerID}/pause", s.handleUISetDesired("paused"))
		router.Post("/verifier-engine-room/workers/{workerID}/resume", s.handleUISetDesired("running"))
		router.Post("/verifier-engine-room/workers/{workerID}/drain", s.handleUISetDesired("draining"))
		router.Post("/verifier-engine-room/workers/{workerID}/stop", s.handleUISetDesired("stopped"))
		router.Post("/verifier-engine-room/pools/{pool}/scale", s.handleUIScalePool)

		router.Handle("/assets/*", http.StripPrefix("/assets/", http.FileServer(http.Dir("assets"))))
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
