# The CREED handbook

The rules for the Wayne State app stack, chapter by chapter. Each chapter
states what the ADRs have decided or what WSU's developer standards (2024)
already require, and names its undecided points under a "Not decided here"
heading.

Every rule that uses MUST, SHOULD or MAY names what holds it, with one of three tags:

- "PROCTOR check: `<id>`" names the PROCTOR check that reports a breach of that rule.
- "Held by: ..." names any other point that holds it: a Preflight component, a runtime package's config, CREED's own CI.
- "No check exists yet" says that nothing does, so the gap is in plain sight.

`composer lint-handbook` fails when a rule carries none of them, so a rule cannot quietly lose its tag.

- [Versions](versions.md)
- [Dependencies](dependencies.md)
- [Code](code.md)
- [Auth](auth.md)
- [Databases](databases.md)
- [Runtime and headers](runtime-and-headers.md)
- [Deploy gate](deploy-gate.md)
- [CI](ci.md)
- [Accessibility](accessibility.md)
- [Every OS](every-os.md)
- [Secrets](secrets.md)
- [AI and LLM use](ai.md)
- [Production](production.md)
- [Overrides](overrides.md)
