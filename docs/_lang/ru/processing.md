# docs/processing.md

## 1. Overview

Система обработки Telegram updates построена как асинхронный pipeline, поддерживающий multiple ingestion sources и at-least-once delivery semantics.

Основные источники входящих событий:

- Poller (long polling Telegram API)
- Webhooks (HTTP inbound updates)
- (опционально) internal system events

Все источники приводят данные к единому формату:

- UpdateContext

---

## 2. Core Principle

Система работает в модели:

> At-least-once processing with idempotent execution

Это означает:

- одно событие может быть обработано несколько раз
- повторная обработка допустима и ожидаема
- система НЕ гарантирует exactly-once execution

---

## 3. Ingestion Layer

### 3.1 Poller

Poller получает updates через Telegram getUpdates API.

Обязанности:

- fetch updates
- normalize to UpdateContext
- forward into processing pipeline

Poller НЕ:
- хранит state выполнения
- ожидает completion execution
- знает результат обработки

---

### 3.2 Webhooks

Webhook endpoint получает updates от Telegram push-режима.

Обязанности:

- validate request
- normalize to UpdateContext
- forward into processing pipeline

Webhook НЕ:
- выполняет business logic
- блокирует HTTP ответ до completion обработки
- хранит state выполнения

---

### 3.3 Unified Ingestion Contract

Все входящие события приводятся к:

- UpdateContext

и передаются дальше без различия источника.

---

## 4. Processing Pipeline

Основной pipeline:

UpdateContext
→ ProcessingDaemon
→ InboxRouter
→ Execution Strategy
→ Scheduler
→ Outbound Layer
→ Telegram API

---

## 5. Routing Layer (InboxRouter)

Router определяет стратегию обработки:

- DirectStrategy
- AsyncStrategy
- QueueStrategy (partitioned execution)

Router НЕ выполняет работу, только принимает решение.

---

## 6. Execution Strategies

### 6.1 DirectStrategy

- immediate enqueue into scheduler
- no ordering guarantees

---

### 6.2 AsyncStrategy

- optional executionKey coordination
- task may be wrapped by coordinator
- enqueued into scheduler

executionKey используется только как ordering hint

---

### 6.3 QueueStrategy (Partitioned)

- context отправляется в partition scheduler
- используется Redis stream / partition queue
- обеспечивает ordered processing per key

---

## 7. Ordering Model

executionKey / partitionKey:

- обеспечивает последовательность выполнения задач внутри ключа
- НЕ является state machine
- НЕ гарантирует completion
- НЕ используется как truth storage

Ordering is:

> scheduling constraint, not execution state

---

## 8. Scheduling Layer

Scheduler отвечает за:

- управление concurrency (fibers / async tasks)
- execution queue
- backpressure control
- dispatch tasks into execution layer

Scheduler НЕ:
- знает про Telegram API
- управляет retry логикой
- управляет ordering state

---

## 9. Execution Layer (Outbound)

Outbound layer отвечает за:

- HTTP execution to Telegram API
- retry logic
- handling Telegram errors (429, network failures)
- floodwait adaptation
- response normalization

Outbound НЕ:
- является source of truth
- хранит workflow state
- принимает routing decisions

---

## 10. Fault Model

Система поддерживает:

### Process crash
- незавершённые задачи могут быть повторно доставлены
- повторная обработка допустима

### Duplicate execution
- допустима
- должна быть безопасна (idempotent handlers)

### Network failure
- retry via executor
- no global consistency guarantees

---

## 11. Deduplication Model

Deduplication работает на уровне:

- jobId
- optional compound keys (jobId + partitionKey)

Deduplication НЕ гарантирует:

- completion state
- success state
- Telegram execution state

---

## 12. State Model

Система разделяет состояния:

### НЕ ХРАНИТ:
- completion of Telegram requests
- global execution state
- “delivered successfully” truth

### МОЖЕТ ХРАНИТЬ:
- dedup keys
- floodwait state (blockedUntil)
- ordering coordination state (executionKey locks)

---

## 13. Multiple Ingestion Sources

Система поддерживает multiple concurrent ingestion sources:

- PollerDaemon
- WebhookReceiver
- (optional) internal producers

Все источники равноправны и:

- используют единый pipeline
- не координируются между собой
- могут создавать дубликаты событий

---

## 14. Consistency Guarantees

Система гарантирует:

- at-least-once delivery into pipeline
- eventual execution under normal conditions
- ordering within executionKey (best-effort / configured)

Система НЕ гарантирует:

- exactly-once execution
- strict global ordering
- immediate completion visibility

---

## 15. Design Constraints

MUST:
- быть устойчивой к дубликатам
- переживать падение процесса
- учитывать retry_after (FloodWait)
- разделять ingestion / routing / execution

MUST NOT:
- превращаться в distributed workflow engine
- использовать Redis как источник истины выполнения
- требовать глобальной синхронизации для корректности

---

## 16. Mental Model

Система =

event ingestion → scheduling constraints → execution engine

НЕ =

distributed transactional workflow system

---

## 17. Summary

- Poller и Webhooks — равные источники событий
- модель at-least-once обязательна
- executionKey = ordering hint, не state
- Outbound = execution engine
- Redis/cache = coordination layer
- дубликаты — нормальное состояние системы
