---
title: Prompt injection
description: Served text steering the AI client, and the controls that limit it.
---

# Prompt injection

An attacker who controls some of the text ADVISOR serves wants the model reading that text to follow hidden instructions, leak data, or trust a false answer.

## Where it applies in ADVISOR

Every tool result is plain text that lands in the client model's context, so anything ADVISOR serves is a possible carrier for instructions aimed at the model:

- `read_project_docs` serves bundled READMEs, best-practices guides and migration notes from the stack repositories (`src/Tools/ReadProjectDocs.php:23`).
- `list_stack_rules` serves CREED handbook text, rule by rule (`src/Tools/ListStackRules.php:70-78`).
- `run_proctor_check` returns finding messages that quote the project's own files (`src/Security/ProctorRunner.php:116`).
- `upgrade_path` returns plan steps and warnings that come from the app's manifests (`src/Security/BoosterRunner.php:209`).
- Tool descriptions, resources and prompts are also text the client reads before any call is made.

The interesting cases are the ones the developer never typed: a document shipped in a stack repository, or a file inside the project that PROCTOR quotes back. Both reach the model inside an answer that looks like a trusted reference, which is exactly what makes them persuasive. The resources (the policy feed, the CREED chapter feeds and the guide feeds) serve the same hash-pinned content as the tools (`src/Module/CoreModule.php:52-83`).

## What stops it

- **Answers are labeled as data.** Every result carries its `source` and a `generated_on` date, and the docs tool says outright that the text is reference material, not instructions (`src/Tools/ReadProjectDocs.php:63-64`, `src/Tools/ReadProjectDocs.php:68`). The rules tool repeats the point (`src/Tools/ListStackRules.php:76`).
- **Decisions stay with PROCTOR.** PROCTOR reports say PROCTOR in CI decides pass or fail (`src/Security/ProctorRunner.php:131`), and Booster plans say they change nothing (`src/Security/BoosterRunner.php:180`), so a served answer cannot claim authority it does not have.
- **Invisible characters are stripped and rejected.** Zero-width, bidirectional and TAG-block controls can hide instructions from a human reviewer. They are removed from child-process output before it is served (`src/Security/ProctorRunner.php:82`, `src/Security/BoosterRunner.php:209`) and refused in bundled content at start-up (`src/Content/ContentBundle.php:98-101`).
- **The published surface is scanned.** Tools, resources and prompts are checked for hidden characters on every test run (`tests/Contract/ServerContractTest.php:70-79`), as are the shipped prompt files themselves (`tests/Contract/ServerContractTest.php:80-82`).
- **Bundled content is hash-pinned.** Every file under `data/` must match its SHA-256 manifest hash or the server throws at start-up (`src/Content/ContentBundle.php:51-58`, `src/Content/ContentBundle.php:95-97`), so a served document cannot change between release and use.
- **Tool metadata is snapshotted.** A quiet change to a tool name or description (tool poisoning) fails the snapshot test instead of shipping unnoticed (`tests/Server/McpServerTest.php:121-138`).
- **ADVISOR talks to no one.** It makes no network calls and no model calls of its own (`docs/guides/safety.md:19`), so there is no second channel for served text to act through.

The layers are deliberate: labeling tells the model what the text is, pinning keeps it stable, stripping removes the hidden channels, and the snapshot test makes quiet changes loud.

A reviewer can check these controls without trusting this page: read the `notice` fields above, run `composer ci` (the contract and snapshot tests are part of it), and diff `tools/list` against the committed snapshot.

## What is left

Labels reduce the risk, but they do not make injected instructions inert. A client model can still be fooled by text it reads, especially text that quotes the project's own files in the middle of a plausible report, and ADVISOR's job is to make sure that text is labeled, pinned and clean, not to guarantee the model will ignore it.

Hash pinning guarantees the text is what was released, not that it is harmless: a compromised upstream repository could ship a malicious document, and the manifest would bless it. Stripping invisible characters catches the sneaky encodings, not the plain-language ones. Nothing in ADVISOR can tell a well-written instruction from a well-written report. The client's own defenses, and the developer's review of what the model does with the text, are the last line.
