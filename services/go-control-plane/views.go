package main

import (
	"encoding/json"
	"html/template"
	"net/http"
	"path/filepath"
)

type ViewRenderer struct {
	templates *template.Template
}

type BasePageData struct {
	Title           string
	Subtitle        string
	ActiveNav       string
	ContentTemplate string
}

func NewViewRenderer() (*ViewRenderer, error) {
	funcMap := template.FuncMap{
		"toJSON": func(value interface{}) template.JS {
			payload, _ := json.Marshal(value)
			return template.JS(payload)
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
