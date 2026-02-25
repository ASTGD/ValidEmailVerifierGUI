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
	store               *Store
	cfg                 Config
	views               *ViewRenderer
	snapshots           *SnapshotStore
	laravelEngineClient *LaravelEngineServerClient
}

func NewServer(store *Store, snapshots *SnapshotStore, cfg Config) *Server {
	renderer, err := NewViewRenderer()
	if err != nil {
		panic(err)
	}

	return &Server{
		store:               store,
		snapshots:           snapshots,
		cfg:                 cfg,
		views:               renderer,
		laravelEngineClient: NewLaravelEngineServerClient(cfg),
	}
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
		router.Post("/api/workers/{workerID}/quarantine", s.handleQuarantineWorker(true))
		router.Post("/api/workers/{workerID}/unquarantine", s.handleQuarantineWorker(false))

		router.Get("/api/pools", s.handlePools)
		router.Post("/api/pools/{pool}/scale", s.handleScalePool)
		router.Get("/api/health/ready", s.handleReadiness)
		router.Get("/api/incidents", s.handleIncidents)
		router.Get("/api/alerts", s.handleAlertsRecords)
		router.Get("/api/slo", s.handleSLO)
		router.Get("/api/providers/health", s.handleProvidersHealth)
		router.Get("/api/providers/quality", s.handleProvidersQuality)
		router.Get("/api/providers/accuracy/calibration", s.handleProvidersAccuracyCalibration)
		router.Get("/api/providers/quality/drift", s.handleProvidersQualityDrift)
		router.Get("/api/providers/unknown/clusters", s.handleProvidersUnknownClusters)
		router.Get("/api/providers/unknown/reasons", s.handleProvidersUnknownReasons)
		router.Get("/api/providers/retry-effectiveness", s.handleProvidersRetryEffectiveness)
		router.Get("/api/providers/modes/semantics", s.handleProviderModeSemantics)
		router.Get("/api/providers/routing/quality", s.handleProviderRoutingQuality)
		router.Get("/api/probe-routing/effectiveness", s.handleProbeRoutingEffectiveness)
		router.Get("/api/routing/effectiveness", s.handleRoutingEffectiveness)
		router.Get("/api/providers/policies", s.handleProviderPolicies)
		router.Post("/api/providers/{provider}/mode", s.handleProviderMode)
		router.Post("/api/providers/policies/reload", s.handleProviderPoliciesReload)
		router.Get("/api/policies/versions", s.handlePolicyVersions)
		router.Post("/api/policies/validate", s.handlePolicyValidate)
		router.Post("/api/policies/promote", s.handlePolicyPromote)
		router.Post("/api/policies/rollback", s.handlePolicyRollback)
		router.Post("/api/policies/shadow/evaluate", s.handlePolicyShadowEvaluate)
		router.Get("/api/policies/shadow/runs", s.handlePolicyShadowRuns)
		router.Get("/api/policies/shadow/compare", s.handlePolicyShadowCompare)
		router.Get("/api/decisions/trace", s.handleDecisionsTrace)
		router.Get("/metrics", s.handleMetrics)

		router.Get("/ui", s.handleUIRedirect)
		router.Get("/ui/overview", s.handleUILegacyRedirect("/verifier-engine-room/overview"))
		router.Get("/ui/workers", s.handleUILegacyRedirect("/verifier-engine-room/workers"))
		router.Get("/ui/registry", s.handleUIRegistryRedirect)
		router.Get("/ui/provisioning", s.handleUILegacyRedirect("/verifier-engine-room/provisioning"))
		router.Get("/ui/servers", s.handleUILegacyRedirect("/verifier-engine-room/servers"))
		router.Get("/ui/servers/{serverID}", s.handleUILegacyServerManageRedirect)
		router.Get("/ui/servers/{serverID}/edit", s.handleUILegacyServerEditRedirect)
		router.Get("/ui/pools", s.handleUILegacyRedirect("/verifier-engine-room/pools"))
		router.Get("/ui/alerts", s.handleUILegacyRedirect("/verifier-engine-room/alerts"))
		router.Get("/ui/settings", s.handleUILegacyRedirect("/verifier-engine-room/settings"))
		router.Get("/ui/events", s.handleUILegacyRedirect("/verifier-engine-room/events"))
		router.Post("/ui/workers/{workerID}/pause", s.requireSameOriginUI(s.handleUISetDesired("paused")))
		router.Post("/ui/workers/{workerID}/resume", s.requireSameOriginUI(s.handleUISetDesired("running")))
		router.Post("/ui/workers/{workerID}/drain", s.requireSameOriginUI(s.handleUISetDesired("draining")))
		router.Post("/ui/workers/{workerID}/stop", s.requireSameOriginUI(s.handleUISetDesired("stopped")))
		router.Post("/ui/workers/{workerID}/quarantine", s.requireSameOriginUI(s.handleUIQuarantine(true)))
		router.Post("/ui/workers/{workerID}/unquarantine", s.requireSameOriginUI(s.handleUIQuarantine(false)))
		router.Post("/ui/workers/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/ui/workers/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/ui/workers/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/ui/workers/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/ui/provisioning/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/ui/provisioning/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/ui/provisioning/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/ui/provisioning/servers/{serverID}/verify", s.requireSameOriginUI(s.handleUIVerifyProvisioningServer))
		router.Post("/ui/provisioning/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/ui/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/ui/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/ui/servers/{serverID}/edit", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/ui/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/ui/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/ui/servers/{serverID}/delete", s.requireSameOriginUI(s.handleUIDeleteEngineServer))
		router.Post("/ui/pools", s.requireSameOriginUI(s.handleUICreateEnginePool))
		router.Post("/ui/pools/{poolID}", s.requireSameOriginUI(s.handleUIUpdateEnginePool))
		router.Post("/ui/pools/{poolID}/archive", s.requireSameOriginUI(s.handleUIArchiveEnginePool))
		router.Post("/ui/pools/{poolID}/set-default", s.requireSameOriginUI(s.handleUISetDefaultEnginePool))
		router.Post("/ui/pools/{pool}/scale", s.requireSameOriginUI(s.handleUIScalePool))
		router.Post("/ui/settings", s.requireSameOriginUI(s.handleUIUpdateSettings))
		router.Post("/ui/settings/rollback", s.requireSameOriginUI(s.handleUIRollbackSettings))
		router.Post("/ui/providers/{provider}/mode", s.requireSameOriginUI(s.handleUIProviderMode))
		router.Post("/ui/providers/policies/reload", s.requireSameOriginUI(s.handleUIProviderPoliciesReload))
		router.Post("/ui/policies/validate", s.requireSameOriginUI(s.handleUIPolicyValidate))
		router.Post("/ui/policies/promote", s.requireSameOriginUI(s.handleUIPolicyPromote))
		router.Post("/ui/policies/rollback", s.requireSameOriginUI(s.handleUIPolicyRollback))

		router.Get("/verifier-engine-room/overview", s.handleUIOverview)
		router.Get("/verifier-engine-room/workers", s.handleUIWorkers)
		router.Get("/verifier-engine-room/registry", s.handleUIRegistryRedirect)
		router.Get("/verifier-engine-room/provisioning", s.handleUIProvisioning)
		router.Get("/verifier-engine-room/servers", s.handleUIServers)
		router.Get("/verifier-engine-room/servers/{serverID}", s.handleUIServerManage)
		router.Get("/verifier-engine-room/servers/{serverID}/edit", s.handleUIServerEdit)
		router.Get("/verifier-engine-room/pools", s.handleUIPools)
		router.Get("/verifier-engine-room/alerts", s.handleUIAlerts)
		router.Get("/verifier-engine-room/settings", s.handleUISettings)
		router.Post("/verifier-engine-room/settings", s.requireSameOriginUI(s.handleUIUpdateSettings))
		router.Post("/verifier-engine-room/settings/rollback", s.requireSameOriginUI(s.handleUIRollbackSettings))
		router.Get("/verifier-engine-room/events", s.handleUIEvents)
		router.Post("/verifier-engine-room/workers/{workerID}/pause", s.requireSameOriginUI(s.handleUISetDesired("paused")))
		router.Post("/verifier-engine-room/workers/{workerID}/resume", s.requireSameOriginUI(s.handleUISetDesired("running")))
		router.Post("/verifier-engine-room/workers/{workerID}/drain", s.requireSameOriginUI(s.handleUISetDesired("draining")))
		router.Post("/verifier-engine-room/workers/{workerID}/stop", s.requireSameOriginUI(s.handleUISetDesired("stopped")))
		router.Post("/verifier-engine-room/workers/{workerID}/quarantine", s.requireSameOriginUI(s.handleUIQuarantine(true)))
		router.Post("/verifier-engine-room/workers/{workerID}/unquarantine", s.requireSameOriginUI(s.handleUIQuarantine(false)))
		router.Post("/verifier-engine-room/workers/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/verifier-engine-room/workers/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/verifier-engine-room/workers/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/verifier-engine-room/workers/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/verifier-engine-room/provisioning/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/verifier-engine-room/provisioning/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/verifier-engine-room/provisioning/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/verifier-engine-room/provisioning/servers/{serverID}/verify", s.requireSameOriginUI(s.handleUIVerifyProvisioningServer))
		router.Post("/verifier-engine-room/provisioning/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/verifier-engine-room/servers", s.requireSameOriginUI(s.handleUICreateEngineServer))
		router.Post("/verifier-engine-room/servers/{serverID}", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/verifier-engine-room/servers/{serverID}/edit", s.requireSameOriginUI(s.handleUIUpdateEngineServer))
		router.Post("/verifier-engine-room/servers/{serverID}/provision", s.requireSameOriginUI(s.handleUIProvisionEngineServer))
		router.Post("/verifier-engine-room/servers/{serverID}/command", s.requireSameOriginUI(s.handleUIEngineServerCommand))
		router.Post("/verifier-engine-room/servers/{serverID}/delete", s.requireSameOriginUI(s.handleUIDeleteEngineServer))
		router.Post("/verifier-engine-room/pools", s.requireSameOriginUI(s.handleUICreateEnginePool))
		router.Post("/verifier-engine-room/pools/{poolID}", s.requireSameOriginUI(s.handleUIUpdateEnginePool))
		router.Post("/verifier-engine-room/pools/{poolID}/archive", s.requireSameOriginUI(s.handleUIArchiveEnginePool))
		router.Post("/verifier-engine-room/pools/{poolID}/set-default", s.requireSameOriginUI(s.handleUISetDefaultEnginePool))
		router.Post("/verifier-engine-room/pools/{pool}/scale", s.requireSameOriginUI(s.handleUIScalePool))
		router.Post("/verifier-engine-room/providers/{provider}/mode", s.requireSameOriginUI(s.handleUIProviderMode))
		router.Post("/verifier-engine-room/providers/policies/reload", s.requireSameOriginUI(s.handleUIProviderPoliciesReload))
		router.Post("/verifier-engine-room/policies/validate", s.requireSameOriginUI(s.handleUIPolicyValidate))
		router.Post("/verifier-engine-room/policies/promote", s.requireSameOriginUI(s.handleUIPolicyPromote))
		router.Post("/verifier-engine-room/policies/rollback", s.requireSameOriginUI(s.handleUIPolicyRollback))

		router.Handle("/assets/*", http.StripPrefix("/assets/", http.FileServer(http.Dir("assets"))))
	})

	return r
}

func (s *Server) ListenAndServe() error {
	addr := ":" + s.cfg.Port
	writeTimeout := 15 * time.Second
	if s.cfg.SSEWriteTimeoutSec > 0 {
		writeTimeout = time.Duration(s.cfg.SSEWriteTimeoutSec) * time.Second
	}
	server := &http.Server{
		Addr:              addr,
		Handler:           s.Router(),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		// Main server uses finite write timeout; SSE endpoint explicitly clears its own write deadline.
		WriteTimeout: writeTimeout,
		IdleTimeout:  30 * time.Second,
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
