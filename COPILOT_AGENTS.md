# AI Coding Agents: Understanding and Guidelines

## About Issue #9 Firewall Warning

### What Happened?

In PR #9, the Copilot agent posted a warning about being blocked by firewall rules while working on improvements to the `deploy/rollback.sh` script. This document explains why this happened and when external web access should be used by AI agents.

### Why Did the Agent Try to Access External URLs?

AI coding agents have access to a `web_fetch` tool that can retrieve external web pages and documentation. The agent may have attempted to:

1. **Verify gcloud command syntax** - The rollback script uses `gcloud container images describe` command
2. **Check Google Cloud Artifact Registry documentation** - To ensure correct API usage
3. **Look up best practices** - For Docker image tag verification

### Was This Necessary?

**No**, it was not necessary in this case. Here's why:

- **Local patterns exist**: The repository already has multiple deployment scripts (`deploy/01-setup-gcp.sh`, `deploy/03-build-push.sh`, etc.) that show `gcloud` command usage
- **Common tools**: `gcloud` CLI and Docker commands are well-established tools with predictable syntax
- **Repository-first approach**: The agent should have examined existing scripts to understand patterns before seeking external documentation

### When SHOULD External Web Access Be Used?

**VALID use cases** (genuinely needed external documentation):

✅ **Official API documentation for external services**
   - Example: "How does Google Cloud Artifact Registry's REST API work?"
   - Example: "What are the authentication requirements for AWS S3 API?"

✅ **Researching unfamiliar technologies**
   - Example: A completely new technology not used anywhere in the repository
   - Example: Documentation for a library being considered for addition

✅ **External library documentation when repository lacks inline docs**
   - Example: Looking up specific PHP library methods not documented in code
   - Example: Checking parameter options for a third-party tool

### When Should Web Access NOT Be Used?

**INVALID use cases** (work with repository instead):

❌ **Making changes to deployment scripts, configs, or CI/CD workflows**
   - Solution: Look at other scripts in `deploy/` directory
   - Solution: Use `grep` to find similar command usage

❌ **Implementing features using technologies already present in codebase**
   - Solution: Search for existing PHP/Bash/Docker patterns in repository
   - Solution: Study similar controllers, models, or scripts

❌ **Looking up common patterns or well-known tools**
   - Solution: Tools like bash, Docker, git, gcloud are well-documented locally
   - Solution: Examine existing usage patterns in the repository

❌ **General "checking" when approach is already known**
   - Solution: Trust existing patterns and test the implementation
   - Solution: Run smoke tests or unit tests to verify behavior

## Best Practices for AI Agents

### 1. Repository-First Approach

**ALWAYS** start by exploring the repository:

```bash
# Search for similar code patterns
grep -r "gcloud container images" deploy/

# Find files with similar functionality
find deploy/ -name "*.sh" -type f

# Study existing implementations
cat deploy/03-build-push.sh
cat deploy/04-deploy.sh
```

### 2. Pattern Recognition

Look at how the repository solves similar problems:

- **Deployment scripts**: Check `deploy/` directory for gcloud patterns
- **PHP features**: Look at similar controllers, models, or library classes
- **Testing**: Study existing tests in `app/tests/unit/` or `tests/smoke-test.sh`
- **Configuration**: Review `app/application/configs/application.ini` and `compose.yaml`

### 3. When External Access Is Genuinely Blocked

If external web access is blocked by firewall rules:

1. **Reassess the need**: Is external documentation actually required?
2. **Use repository context**: 99% of the time, the answer is in the repository
3. **Look at git history**: `git log` and `git show` can reveal intent and patterns
4. **Ask the user**: If truly stuck, ask for clarification rather than guessing

### 4. Self-Contained Work

Most work should be **self-contained** using:

- ✅ Repository files and code
- ✅ Existing patterns and conventions
- ✅ Git history and commit messages
- ✅ README, CLAUDE.md, and copilot-instructions.md
- ✅ Test files that demonstrate usage

## Technical Context: Sandboxed Environments

AI coding agents may run in sandboxed environments with limited internet access. This is by design for security and to ensure work can be:

- **Reproducible**: Not dependent on external sites being available
- **Secure**: No risk of accessing malicious or unexpected content
- **Fast**: No network latency from external requests
- **Self-contained**: All context comes from repository

## For Repository Maintainers

These guidelines have been added to:

1. **`.github/copilot-instructions.md`** - Primary instructions for GitHub Copilot agents
2. **`CLAUDE.md`** - Instructions for Claude AI agents
3. **`GEMINI.md`** - Instructions for Google Gemini agents
4. **`COPILOT_AGENTS.md`** (this file) - Detailed explanation and rationale

All AI agents working with this repository should follow the **repository-first approach** and only access external documentation when genuinely necessary.

## Questions?

If you have questions about AI agent behavior or these guidelines:

1. Review this document and the AI instructions files
2. Check if similar issues have been addressed in closed issues/PRs
3. Open a new issue with the `documentation` or `question` label

---

**Established**: 2026-01-06  
**Related Issues**: #9 (firewall warning during rollback script improvements)
