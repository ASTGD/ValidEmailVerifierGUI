package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"math"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

type OverviewData struct {
	BasePageData
	WorkerCount            int
	PoolCount              int
	DesiredTotal           int
	ErrorRateTotal         float64
	ErrorRateAverage       float64
	IncidentCount          int
	ProbeUnknownRate       float64
	ProbeTempfailRate      float64
	ProbeRejectRate        float64
	LaravelFallbackWorkers int
	ProviderHealth         []ProviderHealthSummary
	ProviderAccuracy       []ProviderAccuracyCalibrationSummary
	UnknownClusters        []ProviderUnknownClusterSummary
	UnknownReasons         []ProviderUnknownReasonSummary
	PolicyShadowRuns       []PolicyShadowRunRecord
	ProviderPolicies       ProviderPoliciesData
	RoutingQuality         RoutingQualitySummary
	Pools                  []PoolSummary
	ChartLabels            []string
	ChartOnline            []int
	ChartDesired           []int
	HistoryLabels          []string
	HistoryWorkers         []int
	HistoryDesired         []int
	HasHistory             bool
}

type WorkersPageData struct {
	BasePageData
	WorkerCount         int
	Workers             []WorkerSummary
	WorkerServerLinks   map[string]WorkerServerLink
	PollIntervalSeconds int
	DecisionTraces      WorkerDecisionTracePageData
}

type ProvisioningPageData struct {
	BasePageData
	Mode                 string
	SelectedServer       *LaravelEngineServerRecord
	Prefill              ProvisioningPrefillHints
	VerificationChecked  bool
	ClaimNextProbe       string
	ClaimNextProbeDetail string
	ServerRegistry       EngineServerRegistryPageData
	InstallCommandCopy   string
	InstallCopyUsesSaved bool
	InstallCopyUsername  string
	InstallCopyError     string
}

type ServersPageData struct {
	BasePageData
	ServerRegistry EngineServerRegistryPageData
}

type ServerManagePageData struct {
	BasePageData
	Server       *LaravelEngineServerRecord
	Notice       string
	Error        string
	DeleteLocked bool
}

type ServerEditPageData struct {
	BasePageData
	Server          *LaravelEngineServerRecord
	VerifierDomains []LaravelVerifierDomainOption
	Pools           []LaravelEnginePoolRecord
	Notice          string
	Error           string
}

type WorkerServerLink struct {
	Matched     bool   `json:"matched"`
	ServerID    int    `json:"server_id,omitempty"`
	ServerName  string `json:"server_name,omitempty"`
	ManageURL   string `json:"manage_url,omitempty"`
	RegisterURL string `json:"register_url,omitempty"`
}

type ProvisioningPrefillHints struct {
	WorkerID string
	Host     string
	IP       string
}

type WorkerDecisionTracePageData struct {
	Enabled      bool
	Error        string
	Records      []LaravelDecisionTraceRecord
	Filter       LaravelDecisionTraceFilter
	NextBeforeID int64
}

type EngineServerRegistryPageData struct {
	Enabled                  bool
	Configured               bool
	Notice                   string
	Error                    string
	Warning                  string
	Servers                  []LaravelEngineServerRecord
	Pools                    []LaravelEnginePoolRecord
	VerifierDomains          []LaravelVerifierDomainOption
	EditServerID             int
	EditServer               *LaravelEngineServerRecord
	ProvisionBundleServerID  int
	ProvisionBundle          *LaravelProvisioningBundleDetails
	ProvisionBundleLoadError string
	Filter                   string
	OrphanWorkers            []WorkerSummary
	RequireRuntimeMatch      bool
}

type PoolsPageData struct {
	BasePageData
	Configured          bool
	Notice              string
	Error               string
	PoolCount           int
	Rows                []PoolInventoryRow
	EditPoolID          int
	EditPool            *LaravelEnginePoolRecord
	ProfileOptions      []PoolProfileOption
	PollIntervalSeconds int
}

type PoolInventoryRow struct {
	Pool               LaravelEnginePoolRecord
	RuntimeOnline      int
	RuntimeDesired     int
	RuntimeHealthScore float64
}

type PoolProfileOption struct {
	Key   string
	Label string
}

type AlertsPageData struct {
	BasePageData
	HasStorage          bool
	AlertCount          int
	Alerts              []AlertRecord
	Incidents           []IncidentRecord
	ActiveIncidentCount int
	PollIntervalSeconds int
}

type SettingsPageData struct {
	BasePageData
	Saved                                     bool
	ProvisioningCredentialsSaved              bool
	RolledBack                                bool
	HasRollbackSnapshot                       bool
	ProvisioningCredentials                   *LaravelProvisioningCredentials
	ProvisioningCredentialsError              string
	Settings                                  RuntimeSettings
	DefaultSettings                           RuntimeSettings
	ProviderHealth                            []ProviderHealthSummary
	ProviderPolicies                          ProviderPoliciesData
	PolicyVersions                            []SMTPPolicyVersionRecord
	PolicyRollouts                            []SMTPPolicyRolloutRecord
	PolicyPayloadStrictValidationEnabled      bool
	PolicyCanaryAutopilotEnabled              bool
	PolicyCanaryWindowMinutes                 int
	PolicyCanaryRequiredHealthWindows         int
	PolicyCanaryUnknownRegressionThreshold    float64
	PolicyCanaryTempfailRecoveryDropThreshold float64
	PolicyCanaryPolicyBlockSpikeThreshold     float64
	RuntimeHelp                               map[string]SettingHelpTip
}

type LivePayload struct {
	Timestamp              string                  `json:"timestamp"`
	WorkerCount            int                     `json:"worker_count"`
	PoolCount              int                     `json:"pool_count"`
	DesiredTotal           int                     `json:"desired_total"`
	ErrorRateTotal         float64                 `json:"error_rate_total"`
	ErrorRateAverage       float64                 `json:"error_rate_average"`
	Pools                  []PoolSummary           `json:"pools"`
	IncidentCount          int                     `json:"incident_count"`
	ProbeUnknownRate       float64                 `json:"probe_unknown_rate"`
	ProbeTempfailRate      float64                 `json:"probe_tempfail_rate"`
	ProbeRejectRate        float64                 `json:"probe_reject_rate"`
	LaravelFallbackWorkers int                     `json:"laravel_fallback_workers"`
	ActivePolicyVersion    string                  `json:"active_policy_version"`
	ProviderHealth         []ProviderHealthSummary `json:"provider_health,omitempty"`
	RoutingQuality         RoutingQualitySummary   `json:"routing_quality"`
	AlertsEnabled          bool                    `json:"alerts_enabled"`
	AutoActionsEnabled     bool                    `json:"auto_actions_enabled"`
	AutoscaleEnabled       bool                    `json:"autoscale_enabled"`
}

func (s *Server) runtimeSettings(ctx context.Context) RuntimeSettings {
	defaults := defaultRuntimeSettings(s.cfg)
	if s.store == nil {
		return defaults
	}

	settings, err := s.store.GetRuntimeSettings(ctx, defaults)
	if err != nil {
		return defaults
	}

	return settings
}

func (s *Server) docsURL() string {
	override := strings.TrimSpace(s.cfg.OpsDocsURL)
	if override != "" {
		return override
	}

	base := strings.TrimRight(strings.TrimSpace(s.cfg.LaravelAPIBaseURL), "/")
	if base == "" {
		return ""
	}

	return base + "/internal/docs"
}

func (s *Server) handleUIOverview(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	labels := make([]string, 0, len(stats.Pools))
	online := make([]int, 0, len(stats.Pools))
	desired := make([]int, 0, len(stats.Pools))
	for _, pool := range stats.Pools {
		labels = append(labels, pool.Pool)
		online = append(online, pool.Online)
		desired = append(desired, pool.Desired)
	}

	data := OverviewData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Overview",
			Subtitle:         "Live telemetry stream",
			ActiveNav:        "overview",
			ContentTemplate:  "overview",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			LiveStreamPath:   "/verifier-engine-room/events",
			ShowProvisioning: true,
		},
		WorkerCount:            stats.WorkerCount,
		PoolCount:              stats.PoolCount,
		DesiredTotal:           stats.DesiredTotal,
		ErrorRateTotal:         stats.ErrorRateTotal,
		ErrorRateAverage:       stats.ErrorRateAverage,
		IncidentCount:          stats.IncidentCount,
		ProbeUnknownRate:       stats.ProbeUnknownRate,
		ProbeTempfailRate:      stats.ProbeTempfailRate,
		ProbeRejectRate:        stats.ProbeRejectRate,
		LaravelFallbackWorkers: stats.LaravelFallbackWorkers,
		ProviderHealth:         stats.ProviderHealth,
		ProviderAccuracy:       stats.ProviderAccuracy,
		UnknownClusters:        stats.UnknownClusters,
		UnknownReasons:         stats.UnknownReasons,
		PolicyShadowRuns:       stats.PolicyShadowRuns,
		ProviderPolicies:       stats.ProviderPolicies,
		RoutingQuality:         stats.RoutingQuality,
		Pools:                  stats.Pools,
		ChartLabels:            labels,
		ChartOnline:            online,
		ChartDesired:           desired,
	}

	if s.snapshots != nil {
		points, snapshotsErr := s.snapshots.GetWorkerSnapshots(r.Context(), 120)
		if snapshotsErr == nil && len(points) > 0 {
			historyLabels := make([]string, 0, len(points))
			historyWorkers := make([]int, 0, len(points))
			historyDesired := make([]int, 0, len(points))
			for _, point := range points {
				historyLabels = append(historyLabels, point.CapturedAt.Format("15:04"))
				historyWorkers = append(historyWorkers, point.TotalWorkers)
				historyDesired = append(historyDesired, point.DesiredTotal)
			}
			data.HistoryLabels = historyLabels
			data.HistoryWorkers = historyWorkers
			data.HistoryDesired = historyDesired
			data.HasHistory = true
		}
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIWorkers(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	settings := s.runtimeSettings(r.Context())

	data := WorkersPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Workers",
			Subtitle:         "Live worker status",
			ActiveNav:        "workers",
			ContentTemplate:  "workers_runtime",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		WorkerCount:         stats.WorkerCount,
		Workers:             stats.Workers,
		WorkerServerLinks:   s.buildWorkerServerLinks(r.Context(), stats.Workers),
		PollIntervalSeconds: settings.UIWorkersRefreshSecond,
		DecisionTraces:      s.buildWorkerDecisionTraceData(r),
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIProvisioning(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	registryData := s.buildEngineServerRegistryData(r, stats.Workers)
	selectedServer := registryData.EditServer
	mode := normalizeProvisioningMode(r.URL.Query().Get("mode"))
	if selectedServer != nil {
		mode = "existing"
	}

	data := ProvisioningPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Provisioning",
			Subtitle:         "Guided server onboarding",
			ActiveNav:        "provisioning",
			ContentTemplate:  "provisioning",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		Mode:                 mode,
		SelectedServer:       selectedServer,
		Prefill:              readProvisioningPrefillHints(r),
		VerificationChecked:  strings.EqualFold(strings.TrimSpace(r.URL.Query().Get("verification")), "checked"),
		ClaimNextProbe:       normalizeClaimNextProbeStatus(r.URL.Query().Get("claim_next_probe")),
		ClaimNextProbeDetail: strings.TrimSpace(r.URL.Query().Get("claim_next_probe_detail")),
		ServerRegistry:       registryData,
	}

	if registryData.ProvisionBundle != nil {
		template := strings.TrimSpace(registryData.ProvisionBundle.InstallCommandTemplate)
		if template != "" {
			data.InstallCommandCopy = template
			credentials, credentialsErr := s.loadProvisioningCredentials(r.Context())
			if credentialsErr != nil {
				data.InstallCopyError = mapRegistryActionError("load provisioning credentials", credentialsErr)
			} else if credentials != nil {
				data.InstallCopyUsername = strings.TrimSpace(credentials.GHCRUsername)
				data.InstallCopyUsesSaved = data.InstallCopyUsername != "" && credentials.GHCRTokenConfigured
			}
		}
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIServers(w http.ResponseWriter, r *http.Request) {
	if editServerID := strings.TrimSpace(firstNonEmptyQueryValue(r, "edit_server_id")); editServerID != "" {
		if parsedID, parseErr := strconv.Atoi(editServerID); parseErr == nil && parsedID > 0 {
			query := url.Values{}
			if notice := firstNonEmptyQueryValue(r, "notice", "registry_notice"); notice != "" {
				query.Set("notice", notice)
			}
			if queryErr := firstNonEmptyQueryValue(r, "error", "registry_error"); queryErr != "" {
				query.Set("error", queryErr)
			}
			target := fmt.Sprintf("/verifier-engine-room/servers/%d/edit", parsedID)
			if encoded := query.Encode(); encoded != "" {
				target += "?" + encoded
			}
			http.Redirect(w, r, target, http.StatusFound)
			return
		}
	}

	if selectedServerID := strings.TrimSpace(firstNonEmptyQueryValue(r, "server_id")); selectedServerID != "" {
		if parsedID, parseErr := strconv.Atoi(selectedServerID); parseErr == nil && parsedID > 0 {
			if firstNonEmptyQueryValue(r, "notice", "error", "registry_notice", "registry_error", "request_id") != "" {
				query := url.Values{}
				if notice := firstNonEmptyQueryValue(r, "notice", "registry_notice"); notice != "" {
					query.Set("notice", notice)
				}
				if queryErr := firstNonEmptyQueryValue(r, "error", "registry_error"); queryErr != "" {
					query.Set("error", queryErr)
				}
				target := fmt.Sprintf("/verifier-engine-room/servers/%d", parsedID)
				if encoded := query.Encode(); encoded != "" {
					target += "?" + encoded
				}
				http.Redirect(w, r, target, http.StatusFound)
				return
			}
		}
	}

	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	registryData := s.buildEngineServerRegistryData(r, stats.Workers)
	data := ServersPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Servers",
			Subtitle:         "Inventory and operational state",
			ActiveNav:        "servers",
			ContentTemplate:  "servers",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		ServerRegistry: registryData,
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIServerManage(w http.ResponseWriter, r *http.Request) {
	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	selectedServer, loadErr := s.loadServerWithRuntimeMatch(r.Context(), serverID)
	if loadErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{mapRegistryActionError("load server registry", loadErr)},
		})
		return
	}
	if selectedServer == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Selected server not found."},
		})
		return
	}

	deleteLocked := serverDeleteLocked(*selectedServer)
	data := ServerManagePageData{
		BasePageData: BasePageData{
			Title:            fmt.Sprintf("Verifier Engine Room · Server · %s", selectedServer.Name),
			Subtitle:         "Server diagnostics and infrastructure control",
			ActiveNav:        "servers",
			ContentTemplate:  "server_manage",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		Server:       selectedServer,
		Notice:       firstNonEmptyQueryValue(r, "notice", "registry_notice"),
		Error:        firstNonEmptyQueryValue(r, "error", "registry_error"),
		DeleteLocked: deleteLocked,
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIServerEdit(w http.ResponseWriter, r *http.Request) {
	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	servers, domains, listErr := s.laravelEngineClient.ListServers(r.Context())
	if listErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{mapRegistryActionError("load server registry", listErr)},
		})
		return
	}

	pools, poolErr := s.laravelEngineClient.ListPools(r.Context())
	if poolErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{mapRegistryActionError("load pools", poolErr)},
		})
		return
	}

	var selectedServer *LaravelEngineServerRecord
	for index := range servers {
		if servers[index].ID == serverID {
			record := servers[index]
			selectedServer = &record
			break
		}
	}
	if selectedServer == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Selected server not found."},
		})
		return
	}

	data := ServerEditPageData{
		BasePageData: BasePageData{
			Title:            fmt.Sprintf("Verifier Engine Room · Edit Server · %s", selectedServer.Name),
			Subtitle:         "Edit server metadata",
			ActiveNav:        "servers",
			ContentTemplate:  "server_edit",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		Server:          selectedServer,
		VerifierDomains: domains,
		Pools:           pools,
		Notice:          firstNonEmptyQueryValue(r, "notice", "registry_notice"),
		Error:           firstNonEmptyQueryValue(r, "error", "registry_error"),
	}

	s.views.Render(w, data)
}

func serverDeleteLocked(server LaravelEngineServerRecord) bool {
	processState := strings.ToLower(strings.TrimSpace(server.ProcessState))
	heartbeatState := strings.ToLower(strings.TrimSpace(server.HeartbeatState))

	if heartbeatState == "healthy" {
		return true
	}
	switch processState {
	case "running", "starting", "stopping":
		return true
	default:
		return false
	}
}

func normalizeProvisioningMode(raw string) string {
	if strings.EqualFold(strings.TrimSpace(raw), "existing") {
		return "existing"
	}

	return "create"
}

func readProvisioningPrefillHints(r *http.Request) ProvisioningPrefillHints {
	return ProvisioningPrefillHints{
		WorkerID: strings.TrimSpace(r.URL.Query().Get("worker_id")),
		Host:     strings.TrimSpace(r.URL.Query().Get("worker_host")),
		IP:       strings.TrimSpace(r.URL.Query().Get("worker_ip")),
	}
}

func (s *Server) buildWorkerDecisionTraceData(r *http.Request) WorkerDecisionTracePageData {
	filter := LaravelDecisionTraceFilter{
		Provider:      strings.ToLower(strings.TrimSpace(r.URL.Query().Get("trace_provider"))),
		DecisionClass: strings.ToLower(strings.TrimSpace(r.URL.Query().Get("trace_decision_class"))),
		ReasonTag:     strings.ToLower(strings.TrimSpace(r.URL.Query().Get("trace_reason_tag"))),
		PolicyVersion: strings.TrimSpace(r.URL.Query().Get("trace_policy_version")),
		Limit:         20,
	}
	if value := strings.TrimSpace(r.URL.Query().Get("trace_limit")); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil && parsed >= 1 && parsed <= 100 {
			filter.Limit = parsed
		}
	}
	if value := strings.TrimSpace(r.URL.Query().Get("trace_before_id")); value != "" {
		if parsed, err := strconv.ParseInt(value, 10, 64); err == nil && parsed > 0 {
			filter.BeforeID = parsed
		}
	}

	data := WorkerDecisionTracePageData{
		Enabled: s.laravelEngineClient != nil,
		Filter:  filter,
	}
	if s.laravelEngineClient == nil {
		data.Error = "Decision trace integration is unavailable. Configure Laravel internal API credentials."
		return data
	}

	response, err := s.laravelEngineClient.ListDecisionTraces(r.Context(), filter)
	if err != nil {
		data.Error = mapRegistryActionError("load decision traces", err)
		return data
	}

	data.Records = response.Records
	data.NextBeforeID = response.NextBeforeID
	return data
}

func (s *Server) buildWorkerServerLinks(ctx context.Context, runtimeWorkers []WorkerSummary) map[string]WorkerServerLink {
	links := make(map[string]WorkerServerLink, len(runtimeWorkers))
	for _, worker := range runtimeWorkers {
		links[worker.WorkerID] = WorkerServerLink{
			RegisterURL: workerProvisioningURL(worker),
		}
	}

	if s.laravelEngineClient == nil {
		return links
	}

	servers, _, err := s.laravelEngineClient.ListServers(ctx)
	if err != nil {
		return links
	}

	servers, _ = applyRegistryRuntimeMatch(servers, runtimeWorkers)
	for _, server := range servers {
		if server.RuntimeMatchStatus != "matched" {
			continue
		}
		workerID := strings.TrimSpace(server.RuntimeMatchWorkerID)
		if workerID == "" {
			continue
		}

		link := links[workerID]
		link.Matched = true
		link.ServerID = server.ID
		link.ServerName = server.Name
		link.ManageURL = fmt.Sprintf("/verifier-engine-room/servers/%d", server.ID)
		if link.RegisterURL == "" {
			link.RegisterURL = "/verifier-engine-room/provisioning?mode=create"
		}
		links[workerID] = link
	}

	return links
}

func workerProvisioningURL(worker WorkerSummary) string {
	query := url.Values{}
	query.Set("mode", "create")
	if worker.WorkerID != "" {
		query.Set("worker_id", worker.WorkerID)
	}
	if strings.TrimSpace(worker.Host) != "" {
		query.Set("worker_host", worker.Host)
	}
	if strings.TrimSpace(worker.IPAddress) != "" {
		query.Set("worker_ip", worker.IPAddress)
	}
	return "/verifier-engine-room/provisioning?" + query.Encode()
}

func (s *Server) buildEngineServerRegistryData(r *http.Request, runtimeWorkers []WorkerSummary) EngineServerRegistryPageData {
	data := EngineServerRegistryPageData{
		Enabled:             true,
		Configured:          s.laravelEngineClient != nil,
		Filter:              normalizeRegistryFilter(r.URL.Query().Get("registry_filter")),
		RequireRuntimeMatch: s.cfg.ServerRegistryRequireRuntimeMatch,
	}

	if !data.Configured {
		data.Error = "Server registry is enabled but Laravel internal API credentials are missing."
		return data
	}

	servers, domains, err := s.laravelEngineClient.ListServers(r.Context())
	if err != nil {
		data.Error = mapRegistryActionError("load server registry", err)
		return data
	}

	pools, poolErr := s.laravelEngineClient.ListPools(r.Context())
	if poolErr != nil {
		data.Error = mapRegistryActionError("load pools", poolErr)
		return data
	}

	servers, orphanWorkers := applyRegistryRuntimeMatch(servers, runtimeWorkers)
	allServers := servers
	data.Servers = filterRegistryServers(allServers, data.Filter)
	data.Pools = pools
	data.VerifierDomains = domains
	data.OrphanWorkers = orphanWorkers

	if notice := firstNonEmptyQueryValue(r, "notice", "registry_notice"); notice != "" {
		data.Notice = notice
	}
	if queryError := firstNonEmptyQueryValue(r, "error", "registry_error"); queryError != "" {
		data.Error = queryError
	}

	editServerID, _ := strconv.Atoi(firstNonEmptyQueryValue(r, "edit_server_id", "server_id"))
	if editServerID > 0 {
		data.EditServerID = editServerID
		for index := range allServers {
			if allServers[index].ID == editServerID {
				server := allServers[index]
				data.EditServer = &server
				break
			}
		}
	}

	provisionBundleServerID, _ := strconv.Atoi(firstNonEmptyQueryValue(r, "bundle_server_id", "server_id"))
	if provisionBundleServerID > 0 {
		data.ProvisionBundleServerID = provisionBundleServerID
		bundle, bundleErr := s.laravelEngineClient.LatestProvisioningBundle(r.Context(), provisionBundleServerID)
		if bundleErr != nil {
			if isNoProvisioningBundleError(bundleErr) {
				data.ProvisionBundle = nil
			} else {
				data.ProvisionBundleLoadError = mapRegistryActionError("load latest provisioning bundle", bundleErr)
			}
		} else {
			data.ProvisionBundle = bundle
		}
	}

	if data.RequireRuntimeMatch {
		mismatchCount := 0
		for _, server := range allServers {
			if server.RuntimeMatchStatus != "matched" {
				mismatchCount++
			}
		}
		if mismatchCount > 0 {
			data.Warning = fmt.Sprintf("Runtime match required: %d server(s) currently have no runtime heartbeat match.", mismatchCount)
		}
	}

	return data
}

func (s *Server) handleUICreateEngineServer(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	payload, parseErr := parseEngineServerUpsertPayload(r)
	if parseErr != nil {
		query := url.Values{
			"error": []string{parseErr.Error()},
		}
		if normalizeProvisioningMode(firstNonEmptyFormValue(r, "mode")) == "create" {
			query.Set("mode", "create")
		}
		if selectedServerID := firstNonEmptyFormValue(r, "server_id", "edit_server_id"); selectedServerID != "" {
			query.Set("server_id", selectedServerID)
		}
		s.redirectServerRegistry(w, r, query)
		return
	}

	createdServer, err := s.laravelEngineClient.CreateServer(r.Context(), payload, uiTriggeredBy(r))
	if err != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{mapRegistryActionError("create server", err)},
		})
		return
	}

	query := url.Values{
		"notice": []string{"Server created successfully."},
	}
	if createdServer != nil && createdServer.ID > 0 {
		serverID := strconv.Itoa(createdServer.ID)
		query.Set("server_id", serverID)
		query.Set("edit_server_id", serverID)
	}
	if normalizeProvisioningMode(firstNonEmptyFormValue(r, "mode")) == "create" {
		query.Set("mode", "existing")
	}

	s.redirectServerRegistry(w, r, query)
}

func (s *Server) handleUIUpdateEngineServer(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	payload, parseErr := parseEngineServerUpsertPayload(r)
	if parseErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{parseErr.Error()},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	if _, err := s.laravelEngineClient.UpdateServer(r.Context(), serverID, payload, uiTriggeredBy(r)); err != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{mapRegistryActionError("update server", err)},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	s.redirectServerRegistry(w, r, url.Values{
		"notice":         []string{"Server updated successfully."},
		"edit_server_id": []string{strconv.Itoa(serverID)},
		"server_id":      []string{strconv.Itoa(serverID)},
	})
}

func (s *Server) handleUIProvisionEngineServer(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	if _, err := s.laravelEngineClient.GenerateProvisioningBundle(r.Context(), serverID, uiTriggeredBy(r)); err != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{mapRegistryActionError("generate provisioning bundle", err)},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	s.redirectServerRegistry(w, r, url.Values{
		"notice":           []string{"Provisioning bundle generated successfully."},
		"bundle_server_id": []string{strconv.Itoa(serverID)},
		"edit_server_id":   []string{strconv.Itoa(serverID)},
		"server_id":        []string{strconv.Itoa(serverID)},
		"mode":             []string{"existing"},
	})
}

func (s *Server) handleUIDeleteEngineServer(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	if _, err := s.laravelEngineClient.DeleteServer(r.Context(), serverID, uiTriggeredBy(r)); err != nil {
		query := url.Values{
			"error": []string{mapRegistryActionError("delete server", err)},
		}
		if registryFilter := normalizeRegistryFilter(firstNonEmptyFormValue(r, "registry_filter")); registryFilter != "all" {
			query.Set("registry_filter", registryFilter)
		}
		s.redirectServerRegistry(w, r, query)
		return
	}

	query := url.Values{
		"notice": []string{"Server deleted successfully."},
	}
	if registryFilter := normalizeRegistryFilter(firstNonEmptyFormValue(r, "registry_filter")); registryFilter != "all" {
		query.Set("registry_filter", registryFilter)
	}
	s.redirectServerRegistry(w, r, query)
}

func (s *Server) handleUIVerifyProvisioningServer(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	query := url.Values{
		"server_id":      []string{strconv.Itoa(serverID)},
		"edit_server_id": []string{strconv.Itoa(serverID)},
		"mode":           []string{"existing"},
		"verification":   []string{"checked"},
	}
	bundleServerID := serverID
	if bundleServerIDRaw := strings.TrimSpace(firstNonEmptyFormValue(r, "bundle_server_id")); bundleServerIDRaw != "" {
		query.Set("bundle_server_id", bundleServerIDRaw)
		if parsedBundleServerID, parseErr := strconv.Atoi(bundleServerIDRaw); parseErr == nil && parsedBundleServerID > 0 {
			bundleServerID = parsedBundleServerID
		}
	}

	selected, selectErr := s.loadServerWithRuntimeMatch(r.Context(), serverID)
	if selectErr != nil {
		query.Set("error", mapRegistryActionError("load server registry", selectErr))
		s.redirectServerRegistry(w, r, query)
		return
	}

	if selected == nil {
		query.Set("error", "Selected server not found.")
		s.redirectServerRegistry(w, r, query)
		return
	}

	if selected.ProcessControlMode == "agent_systemd" && selected.AgentEnabled {
		command, execErr := s.laravelEngineClient.ExecuteServerCommand(
			r.Context(),
			serverID,
			LaravelEngineServerCommandPayload{
				Action: "status",
				Reason: "go-provisioning-verify",
			},
			uiTriggeredBy(r),
		)
		if execErr != nil {
			query.Set("error", mapRegistryActionError("run verification status check", execErr))
			s.redirectServerRegistry(w, r, query)
			return
		}

		if strings.EqualFold(command.Status, "failed") {
			message := strings.TrimSpace(command.ErrorMessage)
			if message == "" {
				message = "Verification status check failed."
			}
			query.Set("error", message)
			s.redirectServerRegistry(w, r, query)
			return
		}

		query.Set("notice", "Verification checks refreshed.")
	} else {
		query.Set("notice", "Verification checks refreshed. Agent status check is unavailable for this server mode.")
	}

	updatedServer, refreshErr := s.loadServerWithRuntimeMatch(r.Context(), serverID)
	if refreshErr != nil {
		query.Set("error", mapRegistryActionError("load server registry", refreshErr))
		s.redirectServerRegistry(w, r, query)
		return
	}
	if updatedServer != nil {
		selected = updatedServer
	}

	if selected.RuntimeMatchStatus != "matched" {
		if strings.EqualFold(selected.ProcessState, "running") {
			query.Set("notice", "Verification checks refreshed. Worker process is running; runtime heartbeat is still warming up.")
		} else {
			query.Set("error", "Runtime heartbeat is missing. Check worker CONTROL_PLANE_BASE_URL and CONTROL_PLANE_TOKEN, then restart the worker container.")
		}
	}

	claimNextProbeStatus, claimNextProbeDetail := s.runProvisioningClaimNextAuthProbe(r.Context(), bundleServerID)
	query.Set("claim_next_probe", claimNextProbeStatus)
	if claimNextProbeDetail != "" {
		query.Set("claim_next_probe_detail", claimNextProbeDetail)
	}

	s.redirectServerRegistry(w, r, query)
}

func (s *Server) runProvisioningClaimNextAuthProbe(ctx context.Context, serverID int) (string, string) {
	if s.laravelEngineClient == nil {
		return "fail", "Laravel internal API is not configured."
	}
	if serverID <= 0 {
		return "fail", "Invalid server id for claim-next auth probe."
	}

	bundle, bundleErr := s.laravelEngineClient.LatestProvisioningBundle(ctx, serverID)
	if bundleErr != nil {
		if isNoProvisioningBundleError(bundleErr) {
			return "fail", "No provisioning bundle found. Generate a bundle in Step 2 first."
		}

		return "fail", mapRegistryActionError("run claim-next auth probe", bundleErr)
	}
	if bundle == nil {
		return "fail", "No provisioning bundle found. Generate a bundle in Step 2 first."
	}

	envURL := strings.TrimSpace(bundle.DownloadURLs["env"])
	if envURL == "" {
		return "fail", "Latest bundle has no worker.env download URL. Generate a fresh bundle."
	}

	timeoutSeconds := s.cfg.LaravelInternalAPITimeoutSeconds
	if timeoutSeconds <= 0 {
		timeoutSeconds = 5
	}
	client := &http.Client{
		Timeout: time.Duration(timeoutSeconds) * time.Second,
	}

	envRequest, requestErr := http.NewRequestWithContext(ctx, http.MethodGet, envURL, nil)
	if requestErr != nil {
		return "fail", "Unable to prepare worker.env probe request."
	}

	envResponse, responseErr := client.Do(envRequest)
	if responseErr != nil {
		return "fail", fmt.Sprintf("Unable to fetch worker.env bundle payload: %s", responseErr.Error())
	}
	defer envResponse.Body.Close()

	envPayload, readErr := io.ReadAll(io.LimitReader(envResponse.Body, 64*1024))
	if readErr != nil {
		return "fail", "Unable to read worker.env bundle payload."
	}
	if envResponse.StatusCode != http.StatusOK {
		return "fail", fmt.Sprintf("Unable to fetch worker.env bundle payload (status %d).", envResponse.StatusCode)
	}

	envMap := parseProvisioningEnvPayload(string(envPayload))
	apiBaseURL := strings.TrimRight(strings.TrimSpace(envMap["ENGINE_API_BASE_URL"]), "/")
	apiToken := strings.TrimSpace(envMap["ENGINE_API_TOKEN"])
	if apiBaseURL == "" {
		return "fail", "ENGINE_API_BASE_URL is missing in worker.env."
	}
	if apiToken == "" {
		return "fail", "ENGINE_API_TOKEN is missing in worker.env."
	}

	probePayload, marshalErr := json.Marshal(map[string]string{
		"worker_id": "probe-auth-only",
	})
	if marshalErr != nil {
		return "fail", "Unable to encode claim-next probe payload."
	}

	claimRequest, claimRequestErr := http.NewRequestWithContext(
		ctx,
		http.MethodPost,
		apiBaseURL+"/api/verifier/chunks/claim-next",
		bytes.NewReader(probePayload),
	)
	if claimRequestErr != nil {
		return "fail", "Unable to prepare claim-next probe request."
	}
	claimRequest.Header.Set("Authorization", "Bearer "+apiToken)
	claimRequest.Header.Set("Accept", "application/json")
	claimRequest.Header.Set("Content-Type", "application/json")

	claimResponse, claimResponseErr := client.Do(claimRequest)
	if claimResponseErr != nil {
		return "fail", fmt.Sprintf("Claim-next probe request failed: %s", claimResponseErr.Error())
	}
	defer claimResponse.Body.Close()

	claimResponseBody, claimReadErr := io.ReadAll(io.LimitReader(claimResponse.Body, 8*1024))
	if claimReadErr != nil {
		claimResponseBody = nil
	}

	requestID := strings.TrimSpace(claimResponse.Header.Get("X-Request-Id"))
	message := extractProbeErrorMessage(claimResponseBody)
	switch claimResponse.StatusCode {
	case http.StatusUnauthorized, http.StatusForbidden:
		if message == "" {
			message = "Verifier API rejected the worker token."
		}
		return "fail", appendProbeRequestID(message, requestID)
	case http.StatusUnprocessableEntity:
		return "pass", appendProbeRequestID("API auth accepted (validation-only probe).", requestID)
	case http.StatusOK, http.StatusNoContent:
		return "pass", appendProbeRequestID("API auth accepted.", requestID)
	case http.StatusTooManyRequests:
		message = fallbackProbeMessage(message, "Verifier API is rate-limited. Retry verification shortly.")
		return "pending", appendProbeRequestID(message, requestID)
	default:
		if claimResponse.StatusCode >= http.StatusInternalServerError {
			message = fallbackProbeMessage(message, "Verifier API is temporarily unavailable. Retry verification shortly.")
			return "pending", appendProbeRequestID(message, requestID)
		}
		message = fallbackProbeMessage(message, fmt.Sprintf("Unexpected verifier API status %d.", claimResponse.StatusCode))
		return "fail", appendProbeRequestID(message, requestID)
	}
}

func parseProvisioningEnvPayload(payload string) map[string]string {
	values := make(map[string]string)
	for _, rawLine := range strings.Split(payload, "\n") {
		line := strings.TrimSpace(rawLine)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		keyValue := strings.SplitN(line, "=", 2)
		if len(keyValue) != 2 {
			continue
		}
		key := strings.TrimSpace(keyValue[0])
		if key == "" {
			continue
		}
		value := strings.TrimSpace(keyValue[1])
		value = strings.Trim(value, "\"'")
		values[key] = value
	}

	return values
}

func extractProbeErrorMessage(payload []byte) string {
	trimmed := strings.TrimSpace(string(payload))
	if trimmed == "" {
		return ""
	}

	var envelope struct {
		Message string `json:"message"`
	}
	if err := json.Unmarshal(payload, &envelope); err == nil {
		message := strings.TrimSpace(envelope.Message)
		if message != "" {
			return message
		}
	}

	if len(trimmed) > 180 {
		return trimmed[:180] + "..."
	}

	return trimmed
}

func fallbackProbeMessage(message string, fallback string) string {
	if strings.TrimSpace(message) != "" {
		return message
	}

	return fallback
}

func appendProbeRequestID(message string, requestID string) string {
	baseMessage := strings.TrimSpace(message)
	if baseMessage == "" {
		baseMessage = "Claim-next auth probe completed."
	}
	requestID = strings.TrimSpace(requestID)
	if requestID == "" {
		return baseMessage
	}

	return fmt.Sprintf("%s (request id: %s)", baseMessage, requestID)
}

func (s *Server) loadServerWithRuntimeMatch(ctx context.Context, serverID int) (*LaravelEngineServerRecord, error) {
	servers, _, err := s.laravelEngineClient.ListServers(ctx)
	if err != nil {
		return nil, err
	}

	stats, statsErr := s.collectControlPlaneStats(ctx)
	if statsErr == nil {
		servers, _ = applyRegistryRuntimeMatch(servers, stats.Workers)
	}

	for index := range servers {
		if servers[index].ID == serverID {
			server := servers[index]
			return &server, nil
		}
	}

	return nil, nil
}

func (s *Server) handleUIEngineServerCommand(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	serverID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "serverID")))
	if err != nil || serverID <= 0 {
		s.redirectServerRegistry(w, r, url.Values{
			"error": []string{"Invalid server id."},
		})
		return
	}

	if parseErr := r.ParseForm(); parseErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{"Invalid command payload."},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	action := strings.ToLower(strings.TrimSpace(r.FormValue("action")))
	switch action {
	case "start", "stop", "restart", "status":
	default:
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{"Invalid command action."},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	command, execErr := s.laravelEngineClient.ExecuteServerCommand(
		r.Context(),
		serverID,
		LaravelEngineServerCommandPayload{
			Action:         action,
			Reason:         strings.TrimSpace(r.FormValue("reason")),
			IdempotencyKey: strings.TrimSpace(r.FormValue("idempotency_key")),
		},
		uiTriggeredBy(r),
	)
	if execErr != nil {
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{mapRegistryActionError("execute server command", execErr)},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	if strings.EqualFold(command.Status, "failed") {
		message := strings.TrimSpace(command.ErrorMessage)
		if message == "" {
			message = "Server command failed."
		}
		s.redirectServerRegistry(w, r, url.Values{
			"error":          []string{message},
			"edit_server_id": []string{strconv.Itoa(serverID)},
			"server_id":      []string{strconv.Itoa(serverID)},
		})
		return
	}

	notice := fmt.Sprintf("Server command %s completed (%s).", action, command.Status)
	if strings.TrimSpace(command.RequestID) != "" {
		notice += fmt.Sprintf(" Request id: %s.", command.RequestID)
	}
	s.redirectServerRegistry(w, r, url.Values{
		"notice":         []string{notice},
		"edit_server_id": []string{strconv.Itoa(serverID)},
		"server_id":      []string{strconv.Itoa(serverID)},
		"request_id":     []string{command.RequestID},
	})
}

func (s *Server) redirectServerRegistry(w http.ResponseWriter, r *http.Request, query url.Values) {
	if query == nil {
		query = url.Values{}
	}
	targetPath := "/verifier-engine-room/servers"
	returnTarget := strings.ToLower(strings.TrimSpace(firstNonEmptyFormValue(r, "return_to", "ui_return_to")))
	serverID := parsePositiveInt(firstNonEmptyFormValue(r, "return_server_id", "server_id", "edit_server_id"))
	switch returnTarget {
	case "provisioning":
		targetPath = "/verifier-engine-room/provisioning"
	case "servers":
		targetPath = "/verifier-engine-room/servers"
	case "server_manage":
		if serverID > 0 {
			targetPath = fmt.Sprintf("/verifier-engine-room/servers/%d", serverID)
		}
	case "server_edit":
		if serverID > 0 {
			targetPath = fmt.Sprintf("/verifier-engine-room/servers/%d/edit", serverID)
		}
	default:
		path := strings.ToLower(strings.TrimSpace(r.URL.Path))
		if strings.Contains(path, "/provisioning/") {
			targetPath = "/verifier-engine-room/provisioning"
		}
		if strings.Contains(path, "/servers/") {
			targetPath = "/verifier-engine-room/servers"
			extractedID := extractServerIDFromPath(path)
			if extractedID > 0 {
				if strings.HasSuffix(path, "/edit") {
					targetPath = fmt.Sprintf("/verifier-engine-room/servers/%d/edit", extractedID)
				} else {
					targetPath = fmt.Sprintf("/verifier-engine-room/servers/%d", extractedID)
				}
			}
		}
	}
	if encodedQuery := query.Encode(); encodedQuery != "" {
		targetPath += "?" + encodedQuery
	}

	http.Redirect(w, r, targetPath, http.StatusSeeOther)
}

func parsePositiveInt(raw string) int {
	parsed, err := strconv.Atoi(strings.TrimSpace(raw))
	if err != nil || parsed <= 0 {
		return 0
	}

	return parsed
}

func extractServerIDFromPath(path string) int {
	parts := strings.Split(strings.Trim(path, "/"), "/")
	for index, part := range parts {
		if part != "servers" || index+1 >= len(parts) {
			continue
		}
		return parsePositiveInt(parts[index+1])
	}

	return 0
}

func firstNonEmptyQueryValue(r *http.Request, keys ...string) string {
	for _, key := range keys {
		if value := strings.TrimSpace(r.URL.Query().Get(key)); value != "" {
			return value
		}
	}

	return ""
}

func firstNonEmptyFormValue(r *http.Request, keys ...string) string {
	for _, key := range keys {
		if value := strings.TrimSpace(r.FormValue(key)); value != "" {
			return value
		}
	}

	return ""
}

func parseEngineServerUpsertPayload(r *http.Request) (LaravelEngineServerUpsertPayload, error) {
	if err := r.ParseForm(); err != nil {
		return LaravelEngineServerUpsertPayload{}, fmt.Errorf("invalid form payload")
	}

	name := strings.TrimSpace(r.FormValue("name"))
	if name == "" {
		return LaravelEngineServerUpsertPayload{}, fmt.Errorf("name is required")
	}

	ipAddress := strings.TrimSpace(r.FormValue("ip_address"))
	if ipAddress == "" {
		return LaravelEngineServerUpsertPayload{}, fmt.Errorf("ip address is required")
	}

	payload := LaravelEngineServerUpsertPayload{
		Name:               name,
		IPAddress:          ipAddress,
		Environment:        strings.TrimSpace(r.FormValue("environment")),
		Region:             strings.TrimSpace(r.FormValue("region")),
		IsActive:           parseFormBoolean(r.FormValue("is_active")),
		DrainMode:          parseFormBoolean(r.FormValue("drain_mode")),
		HeloName:           strings.TrimSpace(r.FormValue("helo_name")),
		MailFromAddress:    strings.TrimSpace(r.FormValue("mail_from_address")),
		Notes:              strings.TrimSpace(r.FormValue("notes")),
		ProcessControlMode: normalizeProcessControlMode(r.FormValue("process_control_mode")),
		AgentEnabled:       parseFormBoolean(r.FormValue("agent_enabled")),
		AgentBaseURL:       strings.TrimSpace(r.FormValue("agent_base_url")),
		AgentVerifyTLS:     true,
		AgentServiceName:   strings.TrimSpace(r.FormValue("agent_service_name")),
	}

	workerPoolIDRaw := strings.TrimSpace(r.FormValue("worker_pool_id"))
	if workerPoolIDRaw == "" {
		return LaravelEngineServerUpsertPayload{}, fmt.Errorf("worker pool is required")
	}
	workerPoolID, err := strconv.Atoi(workerPoolIDRaw)
	if err != nil || workerPoolID < 1 {
		return LaravelEngineServerUpsertPayload{}, fmt.Errorf("worker pool is required")
	}
	payload.WorkerPoolID = &workerPoolID

	if rawAgentVerifyTLS := strings.TrimSpace(r.FormValue("agent_verify_tls")); rawAgentVerifyTLS != "" {
		payload.AgentVerifyTLS = parseFormBoolean(rawAgentVerifyTLS)
	}

	if maxConcurrencyRaw := strings.TrimSpace(r.FormValue("max_concurrency")); maxConcurrencyRaw != "" {
		maxConcurrency, err := strconv.Atoi(maxConcurrencyRaw)
		if err != nil || maxConcurrency < 1 {
			return LaravelEngineServerUpsertPayload{}, fmt.Errorf("max concurrency must be a positive integer")
		}
		payload.MaxConcurrency = &maxConcurrency
	}

	if verifierDomainIDRaw := strings.TrimSpace(r.FormValue("verifier_domain_id")); verifierDomainIDRaw != "" {
		verifierDomainID, err := strconv.Atoi(verifierDomainIDRaw)
		if err != nil || verifierDomainID < 1 {
			return LaravelEngineServerUpsertPayload{}, fmt.Errorf("verifier domain id must be a positive integer")
		}
		payload.VerifierDomainID = &verifierDomainID
	}

	if agentTimeoutRaw := strings.TrimSpace(r.FormValue("agent_timeout_seconds")); agentTimeoutRaw != "" {
		agentTimeout, err := strconv.Atoi(agentTimeoutRaw)
		if err != nil || agentTimeout < 2 || agentTimeout > 30 {
			return LaravelEngineServerUpsertPayload{}, fmt.Errorf("agent timeout must be between 2 and 30 seconds")
		}
		payload.AgentTimeoutSeconds = &agentTimeout
	}

	return payload, nil
}

func normalizeProcessControlMode(raw string) string {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case "agent_systemd":
		return "agent_systemd"
	default:
		return "control_plane_only"
	}
}

func parseFormBoolean(raw string) bool {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case "1", "true", "on", "yes":
		return true
	default:
		return false
	}
}

func poolProfileOptions() []PoolProfileOption {
	return []PoolProfileOption{
		{Key: "standard", Label: "Standard"},
		{Key: "low_hit", Label: "Low Hit"},
		{Key: "warmup", Label: "Warmup"},
	}
}

func normalizePoolProfile(raw string) string {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case "low_hit":
		return "low_hit"
	case "warmup":
		return "warmup"
	default:
		return "standard"
	}
}

func normalizePoolProviderProfiles(source map[string]string) map[string]string {
	return map[string]string{
		"generic":   normalizePoolProfile(source["generic"]),
		"gmail":     normalizePoolProfile(source["gmail"]),
		"microsoft": normalizePoolProfile(source["microsoft"]),
		"yahoo":     normalizePoolProfile(source["yahoo"]),
	}
}

func parseEnginePoolUpsertPayload(r *http.Request) (LaravelEnginePoolUpsertPayload, error) {
	if err := r.ParseForm(); err != nil {
		return LaravelEnginePoolUpsertPayload{}, fmt.Errorf("invalid form payload")
	}

	slug := strings.ToLower(strings.TrimSpace(r.FormValue("slug")))
	if slug == "" {
		return LaravelEnginePoolUpsertPayload{}, fmt.Errorf("pool slug is required")
	}

	name := strings.TrimSpace(r.FormValue("name"))
	if name == "" {
		return LaravelEnginePoolUpsertPayload{}, fmt.Errorf("pool name is required")
	}

	providerProfiles := normalizePoolProviderProfiles(map[string]string{
		"generic":   r.FormValue("provider_profile_generic"),
		"gmail":     r.FormValue("provider_profile_gmail"),
		"microsoft": r.FormValue("provider_profile_microsoft"),
		"yahoo":     r.FormValue("provider_profile_yahoo"),
	})

	return LaravelEnginePoolUpsertPayload{
		Slug:             slug,
		Name:             name,
		Description:      strings.TrimSpace(r.FormValue("description")),
		IsActive:         parseFormBoolean(r.FormValue("is_active")),
		IsDefault:        parseFormBoolean(r.FormValue("is_default")),
		ProviderProfiles: providerProfiles,
	}, nil
}

func (s *Server) loadProvisioningCredentials(ctx context.Context) (*LaravelProvisioningCredentials, error) {
	if s.laravelEngineClient == nil {
		return nil, nil
	}

	credentials, err := s.laravelEngineClient.ProvisioningCredentials(ctx)
	if err != nil {
		return nil, err
	}

	return credentials, nil
}

func mapRegistryActionError(action string, err error) string {
	if err == nil {
		return ""
	}

	apiErr := &LaravelAPIError{}
	if !errors.As(err, &apiErr) {
		return fmt.Sprintf("Unable to %s: %s", action, err.Error())
	}

	requestSuffix := ""
	if strings.TrimSpace(apiErr.RequestID) != "" {
		requestSuffix = fmt.Sprintf(" (request id: %s)", apiErr.RequestID)
	}

	switch apiErr.StatusCode {
	case http.StatusUnauthorized, http.StatusForbidden:
		return "Laravel internal API authentication failed. Check internal token configuration." + requestSuffix
	case http.StatusUnprocessableEntity:
		if message := strings.TrimSpace(apiErr.Message); message != "" && !strings.EqualFold(message, "Validation failed.") {
			return message + requestSuffix
		}
		return "Validation failed. Review the form values and retry." + requestSuffix
	case http.StatusTooManyRequests:
		return "Laravel internal API is rate-limited. Retry shortly." + requestSuffix
	default:
		if apiErr.StatusCode >= 500 {
			return "Laravel internal API is temporarily unavailable. Retry shortly." + requestSuffix
		}

		return fmt.Sprintf("Unable to %s: %s%s", action, apiErr.Message, requestSuffix)
	}
}

func isNoProvisioningBundleError(err error) bool {
	apiErr := &LaravelAPIError{}
	if !errors.As(err, &apiErr) {
		return false
	}
	if apiErr.StatusCode != http.StatusNotFound {
		return false
	}

	return strings.Contains(strings.ToLower(strings.TrimSpace(apiErr.Message)), "no provisioning bundle")
}

func normalizeRegistryFilter(raw string) string {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case "operational", "needs_attention", "all", "matched", "unmatched":
		return strings.ToLower(strings.TrimSpace(raw))
	case "mismatch":
		return "unmatched"
	case "offline":
		return "needs_attention"
	default:
		return "all"
	}
}

func normalizeClaimNextProbeStatus(raw string) string {
	switch strings.ToLower(strings.TrimSpace(raw)) {
	case "pass", "fail", "pending":
		return strings.ToLower(strings.TrimSpace(raw))
	default:
		return ""
	}
}

func applyRegistryRuntimeMatch(servers []LaravelEngineServerRecord, workers []WorkerSummary) ([]LaravelEngineServerRecord, []WorkerSummary) {
	serverIDs := make(map[string]struct{}, len(servers))
	serverIPs := make(map[string]struct{}, len(servers))
	serverNames := make(map[string]struct{}, len(servers))
	workerMatched := make(map[string]bool, len(workers))

	for index := range servers {
		server := &servers[index]
		server.ProcessState = normalizeProcessStateValue(server.ProcessState, server.Status)
		server.HeartbeatState = normalizeHeartbeatStateValue(server.HeartbeatState, server.Status, server.LastHeartbeatAt)
		server.HeartbeatAgeText = heartbeatAgeText(server.LastHeartbeatAt, server.HeartbeatAgeSeconds)
		server.UpdatedAtDisplay = latestServerUpdatedAt(*server)

		serverID := strings.TrimSpace(strconv.Itoa(server.ID))
		serverIDs[serverID] = struct{}{}
		if ip := strings.TrimSpace(server.IPAddress); ip != "" {
			serverIPs[strings.ToLower(ip)] = struct{}{}
		}
		if name := strings.TrimSpace(server.Name); name != "" {
			normalizedName := strings.ToLower(strings.Trim(name, "."))
			serverNames[normalizedName] = struct{}{}
			shortName := strings.Split(normalizedName, ".")[0]
			if shortName != "" {
				serverNames[shortName] = struct{}{}
			}
		}

		server.RuntimeMatchStatus = "no_runtime_heartbeat"
		server.RuntimeMatchWorkerID = ""
		server.RuntimeMatchDetail = "No runtime heartbeat"

		for _, worker := range workers {
			workerID := strings.TrimSpace(worker.WorkerID)
			workerHost := strings.ToLower(strings.TrimSpace(worker.Host))
			workerIP := strings.ToLower(strings.TrimSpace(worker.IPAddress))
			workerServerIDMatch := workerID == serverID
			workerIPMatch := workerIP != "" && strings.EqualFold(workerIP, strings.TrimSpace(server.IPAddress))
			workerHostMatch := hostsLikelyMatch(strings.TrimSpace(server.Name), workerHost)

			if !workerServerIDMatch && !workerIPMatch && !workerHostMatch {
				continue
			}

			server.RuntimeMatchStatus = "matched"
			server.RuntimeMatchWorkerID = worker.WorkerID
			server.RuntimeMatchDetail = fmt.Sprintf("Matched runtime worker %s", worker.WorkerID)
			workerMatched[worker.WorkerID] = true
			break
		}
	}

	orphanWorkers := make([]WorkerSummary, 0)
	for _, worker := range workers {
		if workerMatched[worker.WorkerID] {
			continue
		}

		workerID := strings.TrimSpace(worker.WorkerID)
		workerHost := strings.ToLower(strings.Trim(strings.TrimSpace(worker.Host), "."))
		workerIP := strings.ToLower(strings.TrimSpace(worker.IPAddress))
		if _, exists := serverIDs[workerID]; exists {
			continue
		}
		if _, exists := serverIPs[workerIP]; exists {
			continue
		}
		if _, exists := serverNames[workerHost]; exists {
			continue
		}

		orphanWorkers = append(orphanWorkers, worker)
	}

	return servers, orphanWorkers
}

func hostsLikelyMatch(serverName string, workerHost string) bool {
	serverName = strings.ToLower(strings.Trim(strings.TrimSpace(serverName), "."))
	workerHost = strings.ToLower(strings.Trim(strings.TrimSpace(workerHost), "."))
	if serverName == "" || workerHost == "" {
		return false
	}

	if serverName == workerHost {
		return true
	}

	serverShort := strings.Split(serverName, ".")[0]
	workerShort := strings.Split(workerHost, ".")[0]
	if serverShort != "" && workerShort != "" && serverShort == workerShort {
		return true
	}

	return false
}

func normalizeProcessStateValue(current string, status string) string {
	switch strings.ToLower(strings.TrimSpace(current)) {
	case "running", "stopped", "starting", "stopping":
		return strings.ToLower(strings.TrimSpace(current))
	default:
		switch strings.ToLower(strings.TrimSpace(status)) {
		case "online":
			return "running"
		case "offline":
			return "stopped"
		default:
			return "unknown"
		}
	}
}

func normalizeHeartbeatStateValue(current string, status string, lastHeartbeat string) string {
	switch strings.ToLower(strings.TrimSpace(current)) {
	case "healthy", "stale", "none":
		return strings.ToLower(strings.TrimSpace(current))
	}

	if strings.TrimSpace(lastHeartbeat) == "" {
		return "none"
	}
	if strings.EqualFold(strings.TrimSpace(status), "online") {
		return "healthy"
	}

	return "stale"
}

func latestServerUpdatedAt(server LaravelEngineServerRecord) string {
	candidates := []string{
		strings.TrimSpace(server.LastTransitionAt),
		strings.TrimSpace(server.LastHeartbeatAt),
		strings.TrimSpace(server.LastAgentSeenAt),
	}
	for _, candidate := range candidates {
		if candidate != "" {
			return candidate
		}
	}

	return "-"
}

func heartbeatAgeText(lastHeartbeat string, heartbeatAgeSeconds *int) string {
	lastHeartbeat = strings.TrimSpace(lastHeartbeat)
	if lastHeartbeat == "" {
		return "none"
	}

	if heartbeatAgeSeconds != nil && *heartbeatAgeSeconds >= 0 {
		return formatAgeShort(time.Duration(*heartbeatAgeSeconds) * time.Second)
	}

	parsed, ok := parseTimestampFlexible(lastHeartbeat)
	if !ok {
		return "unknown age"
	}

	elapsed := time.Since(parsed)
	if elapsed < 0 {
		elapsed = 0
	}

	return formatAgeShort(elapsed)
}

func parseTimestampFlexible(raw string) (time.Time, bool) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return time.Time{}, false
	}

	layouts := []string{
		time.RFC3339Nano,
		time.RFC3339,
		"2006-01-02 15:04:05",
	}
	for _, layout := range layouts {
		if parsed, err := time.Parse(layout, raw); err == nil {
			return parsed, true
		}
	}

	return time.Time{}, false
}

func formatAgeShort(duration time.Duration) string {
	if duration < 0 {
		duration = 0
	}

	if duration < time.Minute {
		return fmt.Sprintf("%ds", int(duration.Seconds()))
	}
	if duration < time.Hour {
		return fmt.Sprintf("%dm", int(duration.Minutes()))
	}
	if duration < 24*time.Hour {
		return fmt.Sprintf("%dh", int(duration.Hours()))
	}

	return fmt.Sprintf("%dd", int(duration.Hours()/24))
}

func filterRegistryServers(servers []LaravelEngineServerRecord, filter string) []LaravelEngineServerRecord {
	filtered := make([]LaravelEngineServerRecord, 0, len(servers))
	for _, server := range servers {
		switch filter {
		case "operational":
			if serverNeedsAttention(server) {
				continue
			}
		case "needs_attention":
			if !serverNeedsAttention(server) {
				continue
			}
		case "matched":
			if server.RuntimeMatchStatus != "matched" {
				continue
			}
		case "unmatched", "mismatch":
			if server.RuntimeMatchStatus == "matched" {
				continue
			}
		case "offline":
			if strings.ToLower(strings.TrimSpace(server.Status)) != "offline" {
				continue
			}
		}

		filtered = append(filtered, server)
	}

	return filtered
}

func serverNeedsAttention(server LaravelEngineServerRecord) bool {
	processState := strings.ToLower(strings.TrimSpace(server.ProcessState))
	heartbeatState := strings.ToLower(strings.TrimSpace(server.HeartbeatState))
	runtimeMatch := strings.ToLower(strings.TrimSpace(server.RuntimeMatchStatus))

	if processState != "running" {
		return true
	}
	if heartbeatState != "healthy" {
		return true
	}
	if runtimeMatch != "matched" {
		return true
	}

	return false
}

func (s *Server) handleUIPools(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	settings := s.runtimeSettings(r.Context())

	runtimeByPool := make(map[string]PoolSummary, len(stats.Pools))
	for _, pool := range stats.Pools {
		runtimeByPool[strings.ToLower(strings.TrimSpace(pool.Pool))] = pool
	}

	data := PoolsPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Pools",
			Subtitle:         "Server-group policy and capacity control",
			ActiveNav:        "pools",
			ContentTemplate:  "pools",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		Configured:          s.laravelEngineClient != nil,
		PoolCount:           stats.PoolCount,
		Rows:                []PoolInventoryRow{},
		ProfileOptions:      poolProfileOptions(),
		PollIntervalSeconds: settings.UIPoolsRefreshSecond,
	}

	data.Notice = firstNonEmptyQueryValue(r, "notice")
	data.Error = firstNonEmptyQueryValue(r, "error")

	if s.laravelEngineClient == nil {
		data.Configured = false
		if data.Error == "" {
			data.Error = "Pools management requires Laravel internal API credentials."
		}
		s.views.Render(w, data)
		return
	}

	pools, poolErr := s.laravelEngineClient.ListPools(r.Context())
	if poolErr != nil {
		data.Error = mapRegistryActionError("load pools", poolErr)
		s.views.Render(w, data)
		return
	}

	editPoolID := parsePositiveInt(firstNonEmptyQueryValue(r, "edit_pool_id"))
	for _, pool := range pools {
		key := strings.ToLower(strings.TrimSpace(pool.Slug))
		runtime := runtimeByPool[key]

		row := PoolInventoryRow{
			Pool:               pool,
			RuntimeOnline:      runtime.Online,
			RuntimeDesired:     runtime.Desired,
			RuntimeHealthScore: runtime.HealthScore,
		}
		data.Rows = append(data.Rows, row)

		if editPoolID > 0 && pool.ID == editPoolID {
			poolCopy := pool
			data.EditPoolID = editPoolID
			data.EditPool = &poolCopy
		}
	}

	s.views.Render(w, data)
}

func (s *Server) redirectPools(w http.ResponseWriter, r *http.Request, values url.Values) {
	destination := "/verifier-engine-room/pools"
	if encoded := values.Encode(); encoded != "" {
		destination += "?" + encoded
	}

	http.Redirect(w, r, destination, http.StatusSeeOther)
}

func (s *Server) handleUICreateEnginePool(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	payload, err := parseEnginePoolUpsertPayload(r)
	if err != nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{err.Error()},
		})
		return
	}

	createdPool, createErr := s.laravelEngineClient.CreatePool(r.Context(), payload, uiTriggeredBy(r))
	if createErr != nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{mapRegistryActionError("create pool", createErr)},
		})
		return
	}

	query := url.Values{
		"notice": []string{"Pool created successfully."},
	}
	if createdPool != nil && createdPool.ID > 0 {
		query.Set("edit_pool_id", strconv.Itoa(createdPool.ID))
	}

	s.redirectPools(w, r, query)
}

func (s *Server) handleUIUpdateEnginePool(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	poolID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "poolID")))
	if err != nil || poolID <= 0 {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Invalid pool id."},
		})
		return
	}

	payload, parseErr := parseEnginePoolUpsertPayload(r)
	if parseErr != nil {
		s.redirectPools(w, r, url.Values{
			"error":        []string{parseErr.Error()},
			"edit_pool_id": []string{strconv.Itoa(poolID)},
		})
		return
	}

	if _, updateErr := s.laravelEngineClient.UpdatePool(r.Context(), poolID, payload, uiTriggeredBy(r)); updateErr != nil {
		s.redirectPools(w, r, url.Values{
			"error":        []string{mapRegistryActionError("update pool", updateErr)},
			"edit_pool_id": []string{strconv.Itoa(poolID)},
		})
		return
	}

	s.redirectPools(w, r, url.Values{
		"notice":       []string{"Pool updated successfully."},
		"edit_pool_id": []string{strconv.Itoa(poolID)},
	})
}

func (s *Server) handleUIArchiveEnginePool(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	poolID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "poolID")))
	if err != nil || poolID <= 0 {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Invalid pool id."},
		})
		return
	}

	if _, archiveErr := s.laravelEngineClient.ArchivePool(r.Context(), poolID, uiTriggeredBy(r)); archiveErr != nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{mapRegistryActionError("archive pool", archiveErr)},
		})
		return
	}

	s.redirectPools(w, r, url.Values{
		"notice": []string{"Pool archived successfully."},
	})
}

func (s *Server) handleUISetDefaultEnginePool(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Laravel internal API is not configured."},
		})
		return
	}

	poolID, err := strconv.Atoi(strings.TrimSpace(chi.URLParam(r, "poolID")))
	if err != nil || poolID <= 0 {
		s.redirectPools(w, r, url.Values{
			"error": []string{"Invalid pool id."},
		})
		return
	}

	if _, setDefaultErr := s.laravelEngineClient.SetDefaultPool(r.Context(), poolID, uiTriggeredBy(r)); setDefaultErr != nil {
		s.redirectPools(w, r, url.Values{
			"error": []string{mapRegistryActionError("set default pool", setDefaultErr)},
		})
		return
	}

	s.redirectPools(w, r, url.Values{
		"notice": []string{"Default pool updated successfully."},
	})
}

func (s *Server) handleUIAlerts(w http.ResponseWriter, r *http.Request) {
	settings := s.runtimeSettings(r.Context())
	data := AlertsPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Alerts",
			Subtitle:         "Recent control plane alerts",
			ActiveNav:        "alerts",
			ContentTemplate:  "alerts",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		HasStorage:          s.snapshots != nil,
		PollIntervalSeconds: settings.UIAlertsRefreshSecond,
	}

	if s.snapshots != nil {
		alerts, err := s.snapshots.GetRecentAlerts(r.Context(), 200)
		if err != nil {
			writeError(w, http.StatusInternalServerError, err.Error())
			return
		}
		data.Alerts = alerts
		data.AlertCount = len(alerts)
	}

	incidents, err := s.store.ListIncidents(r.Context(), 100, true)
	if err == nil {
		data.Incidents = incidents
		for _, incident := range incidents {
			if incident.Status == "active" {
				data.ActiveIncidentCount++
			}
		}
	}

	s.views.Render(w, data)
}

func (s *Server) handleUISettings(w http.ResponseWriter, r *http.Request) {
	defaults := defaultRuntimeSettings(s.cfg)
	settings := s.runtimeSettings(r.Context())

	stats, statsErr := s.collectControlPlaneStats(r.Context())
	providerHealth := make([]ProviderHealthSummary, 0)
	providerPolicies := ProviderPoliciesData{
		PolicyEngineEnabled:  settings.ProviderPolicyEngineEnabled,
		AdaptiveRetryEnabled: settings.AdaptiveRetryEnabled,
		AutoProtectEnabled:   settings.ProviderAutoprotectEnabled,
		Modes:                []ProviderModeState{},
	}
	if statsErr == nil {
		providerHealth = stats.ProviderHealth
		providerPolicies = stats.ProviderPolicies
	}
	hasRollbackSnapshot := false
	if s.store != nil {
		hasRollbackSnapshot, _ = s.store.HasRuntimeSettingsSnapshot(r.Context())
	}

	provisioningCredentials, provisioningCredentialsErr := s.loadProvisioningCredentials(r.Context())
	provisioningCredentialsError := ""
	if provisioningCredentialsErr != nil {
		provisioningCredentialsError = mapRegistryActionError("load provisioning credentials", provisioningCredentialsErr)
	}
	if queryError := strings.TrimSpace(r.URL.Query().Get("provisioning_credentials_error")); queryError != "" {
		provisioningCredentialsError = queryError
	}

	data := SettingsPageData{
		BasePageData: BasePageData{
			Title:            "Verifier Engine Room · Settings",
			Subtitle:         "Runtime controls (alerts, safety, autoscale)",
			ActiveNav:        "settings",
			ContentTemplate:  "settings",
			BasePath:         "/verifier-engine-room",
			DocsURL:          s.docsURL(),
			ShowProvisioning: true,
		},
		Saved:                                     r.URL.Query().Get("saved") == "1",
		ProvisioningCredentialsSaved:              r.URL.Query().Get("provisioning_credentials_saved") == "1",
		RolledBack:                                r.URL.Query().Get("rolled_back") == "1",
		HasRollbackSnapshot:                       hasRollbackSnapshot,
		ProvisioningCredentials:                   provisioningCredentials,
		ProvisioningCredentialsError:              provisioningCredentialsError,
		Settings:                                  settings,
		DefaultSettings:                           defaults,
		ProviderHealth:                            providerHealth,
		ProviderPolicies:                          providerPolicies,
		PolicyVersions:                            []SMTPPolicyVersionRecord{},
		PolicyRollouts:                            []SMTPPolicyRolloutRecord{},
		PolicyPayloadStrictValidationEnabled:      s.cfg.PolicyPayloadStrictValidationEnabled,
		PolicyCanaryAutopilotEnabled:              settings.PolicyCanaryAutopilotEnabled,
		PolicyCanaryWindowMinutes:                 settings.PolicyCanaryWindowMinutes,
		PolicyCanaryRequiredHealthWindows:         settings.PolicyCanaryRequiredHealthWindows,
		PolicyCanaryUnknownRegressionThreshold:    settings.PolicyCanaryUnknownRegressionThreshold,
		PolicyCanaryTempfailRecoveryDropThreshold: settings.PolicyCanaryTempfailRecoveryDropThreshold,
		PolicyCanaryPolicyBlockSpikeThreshold:     settings.PolicyCanaryPolicyBlockSpikeThreshold,
		RuntimeHelp:                               buildRuntimeSettingsHelp(defaults, s.docsURL()),
	}

	if versions, _, versionsErr := s.store.ListSMTPPolicyVersions(r.Context()); versionsErr == nil {
		data.PolicyVersions = versions
	}
	if history, historyErr := s.store.GetSMTPPolicyRolloutHistory(r.Context(), 20); historyErr == nil {
		data.PolicyRollouts = history
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIUpdateSettings(w http.ResponseWriter, r *http.Request) {
	defaults := defaultRuntimeSettings(s.cfg)

	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	parseIntRange := func(name string, min int, max int, message string) (int, bool) {
		value, err := strconv.Atoi(r.FormValue(name))
		if err != nil || value < min || value > max {
			writeError(w, http.StatusBadRequest, message)
			return 0, false
		}

		return value, true
	}

	parseFloatRange := func(name string, min float64, max float64, message string) (float64, bool) {
		value, err := strconv.ParseFloat(r.FormValue(name), 64)
		if err != nil || math.IsNaN(value) || math.IsInf(value, 0) || value < min || value > max {
			writeError(w, http.StatusBadRequest, message)
			return 0, false
		}

		return value, true
	}

	threshold, ok := parseFloatRange("alert_error_rate_threshold", 0, 1000000, "alert_error_rate_threshold must be >= 0")
	if !ok {
		return
	}

	grace, ok := parseIntRange("alert_heartbeat_grace_seconds", 1, 86400, "alert_heartbeat_grace_seconds must be between 1 and 86400")
	if !ok {
		return
	}

	cooldown, ok := parseIntRange("alert_cooldown_seconds", 1, 86400, "alert_cooldown_seconds must be between 1 and 86400")
	if !ok {
		return
	}

	alertCheckInterval, ok := parseIntRange("alert_check_interval_seconds", 5, 600, "alert_check_interval_seconds must be between 5 and 600")
	if !ok {
		return
	}

	staleWorkerTTL, ok := parseIntRange("stale_worker_ttl_seconds", 60, 2592000, "stale_worker_ttl_seconds must be between 60 and 2592000")
	if !ok {
		return
	}

	stuckDesiredGrace, ok := parseIntRange("stuck_desired_grace_seconds", 30, 86400, "stuck_desired_grace_seconds must be between 30 and 86400")
	if !ok {
		return
	}

	autoscaleInterval, ok := parseIntRange("autoscale_interval_seconds", 5, 600, "autoscale_interval_seconds must be between 5 and 600")
	if !ok {
		return
	}

	autoscaleCooldown, ok := parseIntRange("autoscale_cooldown_seconds", 10, 86400, "autoscale_cooldown_seconds must be between 10 and 86400")
	if !ok {
		return
	}

	autoscaleMinDesired, ok := parseIntRange("autoscale_min_desired", 0, 1000, "autoscale_min_desired must be between 0 and 1000")
	if !ok {
		return
	}

	autoscaleMaxDesired, ok := parseIntRange("autoscale_max_desired", autoscaleMinDesired, 1000, "autoscale_max_desired must be >= autoscale_min_desired and <= 1000")
	if !ok {
		return
	}

	autoscaleCanary, ok := parseIntRange("autoscale_canary_percent", 1, 100, "autoscale_canary_percent must be between 1 and 100")
	if !ok {
		return
	}

	quarantineThreshold, ok := parseFloatRange("quarantine_error_rate_threshold", 0, 1000000, "quarantine_error_rate_threshold must be >= 0")
	if !ok {
		return
	}

	providerTempfailWarnRate, ok := parseFloatRange("provider_tempfail_warn_rate", 0, 1, "provider_tempfail_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerTempfailCriticalRate, ok := parseFloatRange("provider_tempfail_critical_rate", providerTempfailWarnRate, 1, "provider_tempfail_critical_rate must be >= provider_tempfail_warn_rate and <= 1")
	if !ok {
		return
	}

	providerRejectWarnRate, ok := parseFloatRange("provider_reject_warn_rate", 0, 1, "provider_reject_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerRejectCriticalRate, ok := parseFloatRange("provider_reject_critical_rate", providerRejectWarnRate, 1, "provider_reject_critical_rate must be >= provider_reject_warn_rate and <= 1")
	if !ok {
		return
	}

	providerUnknownWarnRate, ok := parseFloatRange("provider_unknown_warn_rate", 0, 1, "provider_unknown_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerUnknownCriticalRate, ok := parseFloatRange("provider_unknown_critical_rate", providerUnknownWarnRate, 1, "provider_unknown_critical_rate must be >= provider_unknown_warn_rate and <= 1")
	if !ok {
		return
	}

	policyCanaryWindowMinutes, ok := parseIntRange("policy_canary_window_minutes", 1, 240, "policy_canary_window_minutes must be between 1 and 240")
	if !ok {
		return
	}

	policyCanaryRequiredHealthWindows, ok := parseIntRange("policy_canary_required_health_windows", 1, 20, "policy_canary_required_health_windows must be between 1 and 20")
	if !ok {
		return
	}

	policyCanaryUnknownRegressionThreshold, ok := parseFloatRange("policy_canary_unknown_regression_threshold", 0, 1, "policy_canary_unknown_regression_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryTempfailRecoveryDropThreshold, ok := parseFloatRange("policy_canary_tempfail_recovery_drop_threshold", 0, 1, "policy_canary_tempfail_recovery_drop_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryPolicyBlockSpikeThreshold, ok := parseFloatRange("policy_canary_policy_block_spike_threshold", 0, 1, "policy_canary_policy_block_spike_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryMinProviderWorkers, ok := parseIntRange("policy_canary_min_provider_workers", 1, 1000, "policy_canary_min_provider_workers must be between 1 and 1000")
	if !ok {
		return
	}

	overviewLiveInterval, ok := parseIntRange("ui_overview_live_interval_seconds", 2, 60, "ui_overview_live_interval_seconds must be between 2 and 60")
	if !ok {
		return
	}

	workersRefreshInterval, ok := parseIntRange("ui_workers_refresh_seconds", 2, 120, "ui_workers_refresh_seconds must be between 2 and 120")
	if !ok {
		return
	}

	poolsRefreshInterval, ok := parseIntRange("ui_pools_refresh_seconds", 2, 120, "ui_pools_refresh_seconds must be between 2 and 120")
	if !ok {
		return
	}

	alertsRefreshInterval, ok := parseIntRange("ui_alerts_refresh_seconds", 5, 300, "ui_alerts_refresh_seconds must be between 5 and 300")
	if !ok {
		return
	}

	settings := RuntimeSettings{
		AlertsEnabled:                             r.FormValue("alerts_enabled") != "",
		AutoActionsEnabled:                        r.FormValue("auto_actions_enabled") != "",
		ProviderPolicyEngineEnabled:               r.FormValue("provider_policy_engine_enabled") != "",
		AdaptiveRetryEnabled:                      r.FormValue("adaptive_retry_enabled") != "",
		ProviderAutoprotectEnabled:                r.FormValue("provider_autoprotect_enabled") != "",
		AlertErrorRateThreshold:                   threshold,
		AlertHeartbeatGraceSecond:                 grace,
		AlertCooldownSecond:                       cooldown,
		AlertCheckIntervalSecond:                  alertCheckInterval,
		StaleWorkerTTLSecond:                      staleWorkerTTL,
		StuckDesiredGraceSecond:                   stuckDesiredGrace,
		AutoscaleEnabled:                          r.FormValue("autoscale_enabled") != "",
		AutoscaleIntervalSecond:                   autoscaleInterval,
		AutoscaleCooldownSecond:                   autoscaleCooldown,
		AutoscaleMinDesired:                       autoscaleMinDesired,
		AutoscaleMaxDesired:                       autoscaleMaxDesired,
		AutoscaleCanaryPercent:                    autoscaleCanary,
		QuarantineErrorRateThreshold:              quarantineThreshold,
		ProviderTempfailWarnRate:                  providerTempfailWarnRate,
		ProviderTempfailCriticalRate:              providerTempfailCriticalRate,
		ProviderRejectWarnRate:                    providerRejectWarnRate,
		ProviderRejectCriticalRate:                providerRejectCriticalRate,
		ProviderUnknownWarnRate:                   providerUnknownWarnRate,
		ProviderUnknownCriticalRate:               providerUnknownCriticalRate,
		PolicyCanaryAutopilotEnabled:              r.FormValue("policy_canary_autopilot_enabled") != "",
		PolicyCanaryWindowMinutes:                 policyCanaryWindowMinutes,
		PolicyCanaryRequiredHealthWindows:         policyCanaryRequiredHealthWindows,
		PolicyCanaryUnknownRegressionThreshold:    policyCanaryUnknownRegressionThreshold,
		PolicyCanaryTempfailRecoveryDropThreshold: policyCanaryTempfailRecoveryDropThreshold,
		PolicyCanaryPolicyBlockSpikeThreshold:     policyCanaryPolicyBlockSpikeThreshold,
		PolicyCanaryMinProviderWorkers:            policyCanaryMinProviderWorkers,
		UIOverviewLiveIntervalSecond:              overviewLiveInterval,
		UIWorkersRefreshSecond:                    workersRefreshInterval,
		UIPoolsRefreshSecond:                      poolsRefreshInterval,
		UIAlertsRefreshSecond:                     alertsRefreshInterval,
	}

	if err := validateRuntimeSettingsRisk(defaults, settings); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}

	if s.store == nil {
		writeError(w, http.StatusInternalServerError, "store is not configured")
		return
	}

	if err := s.store.SaveRuntimeSettings(r.Context(), settings); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func formatRuntimeSettingValue(value float64) string {
	if math.Abs(value-math.Round(value)) < 0.0001 {
		return strconv.Itoa(int(math.Round(value)))
	}

	return fmt.Sprintf("%.2f", value)
}

func validateRuntimeSettingsRisk(defaults RuntimeSettings, settings RuntimeSettings) error {
	if key, tip, value, found := firstUnsafeRuntimeSetting(defaults, settings); found {
		return fmt.Errorf(
			"%s is unsafe at %s (safe: %s, caution: %s)",
			key,
			formatRuntimeSettingValue(value),
			tip.RecommendedRangeLabel,
			tip.CautionRangeLabel,
		)
	}

	return nil
}

func (s *Server) handleUIRollbackSettings(w http.ResponseWriter, r *http.Request) {
	if s.store == nil {
		writeError(w, http.StatusInternalServerError, "store is not configured")
		return
	}

	if err := s.store.RollbackRuntimeSettings(r.Context()); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1&rolled_back=1", http.StatusSeeOther)
}

func (s *Server) handleUIUpdateProvisioningCredentials(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		http.Redirect(w, r, "/verifier-engine-room/settings?provisioning_credentials_error=Laravel+internal+API+is+not+configured.", http.StatusSeeOther)
		return
	}

	if err := r.ParseForm(); err != nil {
		http.Redirect(w, r, "/verifier-engine-room/settings?provisioning_credentials_error=Invalid+provisioning+credential+payload.", http.StatusSeeOther)
		return
	}

	ghcrUsername := strings.TrimSpace(r.FormValue("ghcr_username"))
	ghcrToken := strings.TrimSpace(r.FormValue("ghcr_token"))
	clearGHCRToken := parseFormBoolean(r.FormValue("clear_ghcr_token"))
	if ghcrUsername == "" {
		http.Redirect(w, r, "/verifier-engine-room/settings?provisioning_credentials_error=GHCR+username+is+required.", http.StatusSeeOther)
		return
	}

	payload := LaravelProvisioningCredentialsUpdatePayload{
		GHCRUsername:   ghcrUsername,
		ClearGHCRToken: clearGHCRToken,
	}
	if ghcrToken != "" {
		payload.GHCRToken = ghcrToken
	}

	if _, err := s.laravelEngineClient.UpdateProvisioningCredentials(r.Context(), payload, uiTriggeredBy(r)); err != nil {
		errorMessage := mapRegistryActionError("save provisioning credentials", err)
		http.Redirect(
			w,
			r,
			"/verifier-engine-room/settings?provisioning_credentials_error="+url.QueryEscape(errorMessage),
			http.StatusSeeOther,
		)
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?provisioning_credentials_saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIRevealProvisioningCredentials(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{
			"error": "Laravel internal API is not configured.",
		})
		return
	}

	token, err := s.laravelEngineClient.RevealProvisioningCredentials(r.Context())
	if err != nil {
		statusCode := http.StatusBadGateway
		requestID := ""
		apiErr := &LaravelAPIError{}
		if errors.As(err, &apiErr) {
			requestID = strings.TrimSpace(apiErr.RequestID)
			switch apiErr.StatusCode {
			case http.StatusNotFound:
				statusCode = http.StatusNotFound
			case http.StatusUnprocessableEntity:
				statusCode = http.StatusUnprocessableEntity
			case http.StatusTooManyRequests:
				statusCode = http.StatusTooManyRequests
			case http.StatusUnauthorized, http.StatusForbidden:
				statusCode = http.StatusBadGateway
			default:
				if apiErr.StatusCode >= 500 {
					statusCode = http.StatusBadGateway
				} else if apiErr.StatusCode >= 400 {
					statusCode = http.StatusBadRequest
				}
			}
		}

		payload := map[string]string{
			"error": mapRegistryActionError("reveal provisioning credentials", err),
		}
		if requestID != "" {
			payload["request_id"] = requestID
		}
		writeJSON(w, statusCode, payload)
		return
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"data": map[string]string{
			"ghcr_token": token,
		},
	})
}

func (s *Server) handleUIProviderMode(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	provider := chi.URLParam(r, "provider")
	mode := strings.TrimSpace(r.FormValue("mode"))
	if _, err := s.store.SetProviderMode(r.Context(), provider, mode, "manual"); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIProviderPoliciesReload(w http.ResponseWriter, r *http.Request) {
	if _, err := s.store.MarkProviderPoliciesReloaded(r.Context()); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyValidate(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	version := strings.TrimSpace(r.FormValue("policy_version"))
	if version == "" {
		writeError(w, http.StatusBadRequest, "policy_version is required")
		return
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if _, err := s.store.ValidateSMTPPolicyVersion(r.Context(), version, uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyPromote(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	version := strings.TrimSpace(r.FormValue("policy_version"))
	if version == "" {
		writeError(w, http.StatusBadRequest, "policy_version is required")
		return
	}

	canaryPercent := 100
	if value := strings.TrimSpace(r.FormValue("canary_percent")); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil || parsed < 1 || parsed > 100 {
			writeError(w, http.StatusBadRequest, "canary_percent must be between 1 and 100")
			return
		}
		canaryPercent = parsed
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if err := s.guardPolicyPromoteByShadowCompare(r.Context(), version); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}

	if _, err := s.store.PromoteSMTPPolicyVersion(r.Context(), version, canaryPercent, uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyRollback(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if _, err := s.store.RollbackSMTPPolicyVersion(r.Context(), uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func uiTriggeredBy(r *http.Request) string {
	username, _, ok := r.BasicAuth()
	if !ok {
		return "ui"
	}

	username = strings.TrimSpace(username)
	if username == "" {
		return "ui"
	}

	return username
}

func (s *Server) handleUIEvents(w http.ResponseWriter, r *http.Request) {
	flusher, ok := w.(http.Flusher)
	if !ok {
		writeError(w, http.StatusInternalServerError, "streaming not supported")
		return
	}

	// Clear per-request write deadline for long-lived SSE stream while keeping finite server WriteTimeout.
	if controller := http.NewResponseController(w); controller != nil {
		_ = controller.SetWriteDeadline(time.Time{})
	}

	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")

	s.pushLiveEvent(w, r)
	flusher.Flush()

	settings := s.runtimeSettings(r.Context())
	interval := settings.UIOverviewLiveIntervalSecond
	if interval <= 0 {
		interval = 5
	}

	ticker := time.NewTicker(time.Duration(interval) * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-r.Context().Done():
			return
		case <-ticker.C:
			s.pushLiveEvent(w, r)
			flusher.Flush()
		}
	}
}

func (s *Server) pushLiveEvent(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
	defer cancel()

	stats, err := s.collectControlPlaneStats(ctx)
	if err != nil {
		return
	}

	payload := LivePayload{
		Timestamp:              time.Now().UTC().Format(time.RFC3339),
		WorkerCount:            stats.WorkerCount,
		PoolCount:              stats.PoolCount,
		DesiredTotal:           stats.DesiredTotal,
		ErrorRateTotal:         stats.ErrorRateTotal,
		ErrorRateAverage:       stats.ErrorRateAverage,
		Pools:                  stats.Pools,
		IncidentCount:          stats.IncidentCount,
		ProbeUnknownRate:       stats.ProbeUnknownRate,
		ProbeTempfailRate:      stats.ProbeTempfailRate,
		ProbeRejectRate:        stats.ProbeRejectRate,
		LaravelFallbackWorkers: stats.LaravelFallbackWorkers,
		ActivePolicyVersion:    stats.ProviderPolicies.ActiveVersion,
		ProviderHealth:         stats.ProviderHealth,
		RoutingQuality:         stats.RoutingQuality,
		AlertsEnabled:          stats.Settings.AlertsEnabled,
		AutoActionsEnabled:     stats.Settings.AutoActionsEnabled,
		AutoscaleEnabled:       stats.Settings.AutoscaleEnabled,
	}

	data, err := json.Marshal(payload)
	if err != nil {
		return
	}

	_, _ = fmt.Fprintf(w, "event: stats\ndata: %s\n\n", data)
}

func (s *Server) handleUISetDesired(state string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workerID := chi.URLParam(r, "workerID")
		if workerID == "" {
			writeError(w, http.StatusBadRequest, "workerID is required")
			return
		}

		if err := s.store.SetDesiredState(r.Context(), workerID, state); err != nil {
			writeError(w, http.StatusBadRequest, err.Error())
			return
		}

		http.Redirect(w, r, "/verifier-engine-room/workers", http.StatusSeeOther)
	}
}

func (s *Server) handleUIQuarantine(enabled bool) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workerID := chi.URLParam(r, "workerID")
		if workerID == "" {
			writeError(w, http.StatusBadRequest, "workerID is required")
			return
		}

		reason := ""
		if enabled {
			reason = "manual_ui_action"
		}

		if err := s.store.SetWorkerQuarantined(r.Context(), workerID, enabled, reason); err != nil {
			writeError(w, http.StatusBadRequest, err.Error())
			return
		}

		http.Redirect(w, r, "/verifier-engine-room/workers", http.StatusSeeOther)
	}
}

func (s *Server) handleUIScalePool(w http.ResponseWriter, r *http.Request) {
	pool := chi.URLParam(r, "pool")
	if pool == "" {
		writeError(w, http.StatusBadRequest, "pool is required")
		return
	}

	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	desiredValue := r.FormValue("desired")
	desired, err := strconv.Atoi(desiredValue)
	if err != nil {
		writeError(w, http.StatusBadRequest, "desired must be a number")
		return
	}

	if err := s.store.SetPoolDesiredCount(r.Context(), pool, desired); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/pools", http.StatusSeeOther)
}
