# AI and LLM use

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Where models are called

- Model calls MUST be made server-side only, never from browser code. No check exists yet.
- API tokens MUST live in server configuration or the environment and MUST NOT appear in `VITE_*` variables or any client-side code (anything that ships in the bundle is public). Held by: the general secret scan, Preflight's `secret-detection` component (Preflight 0.9.0), for committed tokens only; a token in a `VITE_*` variable is not checked yet.
- Rate limits MUST apply per signed-in user, so one person cannot spend the whole app's budget. No check exists yet.

## Which service

- WarriorGPT, the university-run service, comes first: an app SHOULD use it before any outside provider. No check exists yet.
- Outside model providers (OpenAI, Anthropic, Google and the like) MUST only ever receive public data. Any university data, whether internal or confidential, MUST go to WarriorGPT only. No check exists yet.

## Data in prompts and logs

- Personal data (student, employee or patient records, or identifiers such as AccessIDs, student IDs or Social Security numbers) MAY go to WarriorGPT only, and only the minimum the task needs. No check exists yet.
- Personal data MUST NOT go to an outside model provider (an open stack question, an open stack question). No check exists yet.
- Logs SHOULD keep metadata (user, model, token counts, latency) and MUST NOT keep prompt or answer bodies by default. No check exists yet.
- If bodies are kept for debugging, retention MUST be short and personal data MUST be stripped first. No check exists yet.

## What people see and decide

- AI-written output MUST be labelled as AI-written in the UI. No check exists yet.
- A model MUST NOT make the final call on a decision about a person; a person makes it and is accountable. No check exists yet.

WarriorGPT opens the show; the outside bands play only what is public.

## Not decided here

- Prompt-injection handling is not decided here.
- Evaluation of model quality is not decided here.
