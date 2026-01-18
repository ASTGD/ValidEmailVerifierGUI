# syntax=docker/dockerfile:1
FROM --platform=$BUILDPLATFORM golang:1.22 AS builder

WORKDIR /src
COPY . .

ARG TARGETOS
ARG TARGETARCH
ENV CGO_ENABLED=0

RUN GOOS=${TARGETOS:-linux} GOARCH=${TARGETARCH:-amd64} \
    go build -o /out/engine-worker ./cmd/worker

FROM gcr.io/distroless/base-debian12

WORKDIR /app
COPY --from=builder /out/engine-worker /app/engine-worker

USER nonroot:nonroot
ENTRYPOINT ["/app/engine-worker"]
