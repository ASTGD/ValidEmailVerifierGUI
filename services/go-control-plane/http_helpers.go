package main

import (
	"encoding/json"
	"net/http"
	"net/url"
	"strings"
)

func (s *Server) authMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if s.validateBearer(r) || s.validateBasic(r) {
			next.ServeHTTP(w, r)
			return
		}

		w.Header().Set("WWW-Authenticate", "Basic realm=\"Go Control Plane\"")
		writeError(w, http.StatusUnauthorized, "unauthorized")
		return
	})
}

func (s *Server) validateBearer(r *http.Request) bool {
	token := r.Header.Get("Authorization")
	if token == "" || !strings.HasPrefix(token, "Bearer ") {
		return false
	}
	provided := strings.TrimSpace(strings.TrimPrefix(token, "Bearer "))
	return provided == s.cfg.ControlPlaneToken
}

func (s *Server) validateBasic(r *http.Request) bool {
	username, password, ok := r.BasicAuth()
	if !ok || username == "" || password == "" {
		return false
	}
	return password == s.cfg.ControlPlaneToken
}

func writeJSON(w http.ResponseWriter, status int, payload interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func writeError(w http.ResponseWriter, status int, message string) {
	writeJSON(w, status, map[string]string{"error": message})
}

func (s *Server) requireSameOriginUI(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !s.isSameOriginUIRequest(r) {
			writeError(w, http.StatusForbidden, "forbidden")
			return
		}

		next(w, r)
	}
}

func (s *Server) isSameOriginUIRequest(r *http.Request) bool {
	requestHost := normalizeHost(r.Host)
	if requestHost == "" {
		return false
	}

	if origin := strings.TrimSpace(r.Header.Get("Origin")); origin != "" {
		parsed, err := url.Parse(origin)
		if err != nil {
			return false
		}
		return normalizeHost(parsed.Host) == requestHost
	}

	if referer := strings.TrimSpace(r.Header.Get("Referer")); referer != "" {
		parsed, err := url.Parse(referer)
		if err != nil {
			return false
		}
		return normalizeHost(parsed.Host) == requestHost
	}

	return false
}

func normalizeHost(host string) string {
	return strings.ToLower(strings.TrimSpace(host))
}
