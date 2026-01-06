# Copilot Instructions Setup

This repository uses GitHub Copilot custom instructions to provide AI-powered coding assistance tailored to this codebase.

## Structure

### Repository-Wide Instructions
**File**: `.github/copilot-instructions.md`

This file was created by Copilot in VS Code based on the codebase and contains comprehensive guidance about:
- Project architecture and technology stack
- Development workflow and environment setup
- Key conventions and patterns (MVC, routing, models)
- Configuration and environment variables
- Testing and CI/CD practices
- Deployment procedures
- Critical safety rules (e.g., database modifications)

### Path-Specific Instructions
**Directory**: `.github/instructions/`

These files provide specialized guidance for specific parts of the codebase:

#### `models.instructions.md`
- Applies to: `app/application/models/**/*.php`
- Covers: Two-tier Api/Container architecture pattern
- Key topics: Singleton pattern, data access layer, DTOs, naming conventions

#### `controllers.instructions.md`
- Applies to: `app/application/controllers/**/*.php`
- Covers: Zend Framework 1 controller patterns
- Key topics: Action methods, request/response handling, authentication, routing

#### `tests.instructions.md`
- Applies to: `app/tests/**/*.php`
- Covers: PHPUnit testing patterns and standards
- Key topics: Unit tests, mocking, test structure, coverage expectations

#### `library.instructions.md`
- Applies to: `app/library/Jkl/**/*.php`
- Covers: Custom utility library classes
- Key topics: PSR-0 naming, static methods, string/date/URL utilities, Open Graph

## How It Works

When you use GitHub Copilot in this repository:

1. **Repository-wide instructions** are always active and provide general context
2. **Path-specific instructions** activate automatically when editing files matching their `applyTo` glob pattern
3. Instructions are combined, so you get both general and specialized guidance

## Benefits

- **Faster onboarding**: AI understands project patterns immediately
- **Consistent code**: AI suggestions follow existing conventions
- **Better suggestions**: Context-aware completions and explanations
- **Documentation**: Instructions serve as living documentation

## Maintenance

- **Update instructions** when architectural patterns change
- **Add new instruction files** for new specialized areas (e.g., views, configurations)
- **Keep synchronized** with actual codebase conventions

## Related Files

This repository also has AI agent instruction files for other AI assistants:
- `CLAUDE.md` - Instructions for Claude Code (claude.ai/code)
- `GEMINI.md` - Instructions for Google Gemini

## References

- [GitHub Docs: Adding custom instructions for GitHub Copilot](https://docs.github.com/en/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot)
- [GitHub Best Practices for Copilot Coding Agent](https://gh.io/copilot-coding-agent-tips)

## Setup Date

Initial setup: 2026-01-06

The `.github/copilot-instructions.md` file was created by Copilot in VS Code and served as the foundation for this setup.
