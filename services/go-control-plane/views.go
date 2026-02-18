package main

import (
	"encoding/json"
	"html/template"
	"net/http"
	"path/filepath"
	"strings"
	"time"
)

type ViewRenderer struct {
	templates *template.Template
}

type BasePageData struct {
	Title            string
	Subtitle         string
	ActiveNav        string
	ContentTemplate  string
	BasePath         string
	DocsURL          string
	LiveStreamPath   string
	AutoReloadOnLive bool
}

func NewViewRenderer() (*ViewRenderer, error) {
	funcMap := template.FuncMap{
		"toJSON": func(value interface{}) template.JS {
			payload, _ := json.Marshal(value)
			return template.JS(payload)
		},
		"severityClass": func(severity string) string {
			switch strings.ToLower(strings.TrimSpace(severity)) {
			case "critical":
				return "bg-red-500/20 text-red-300"
			case "warning":
				return "bg-amber-500/20 text-amber-300"
			default:
				return "bg-slate-800 text-slate-200"
			}
		},
		"formatTimestamp": func(value time.Time) string {
			if value.IsZero() {
				return "-"
			}
			return value.UTC().Format("2006-01-02 15:04:05 UTC")
		},
	}

	pattern := filepath.Join("templates", "*.html")
	templates, err := template.New("layout").Funcs(funcMap).ParseGlob(pattern)
	if err != nil {
		return nil, err
	}

	return &ViewRenderer{templates: templates}, nil
}

func (v *ViewRenderer) Render(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := v.templates.ExecuteTemplate(w, "layout", data); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
	}
}
