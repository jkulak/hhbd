# HHBD Deployment Scripts

This directory contains scripts and configuration for deploying HHBD to Google Cloud Platform.

## Image Tagging Strategy

The deployment uses a multi-tag strategy to ensure safe and traceable deployments:

### Tag Types

1. **SHA tags** (`sha-<commit>`): Immutable tags for specific commits
   - Created during CI build for every commit to `prod` branch
   - Example: `sha-abc123f`
   - Used for production deployments to ensure tested code is deployed

2. **Latest tag** (`latest`): Mutable convenience tag
   - Updated during every CI build
   - Points to the most recently built commit
   - Can be used for manual deployments (not recommended for production)
   - Defaults for deployment script when `APP_TAG`/`NGINX_TAG` not specified

3. **Production Last-Known-Good** (`prod-lkg`): Stable production tag
   - Only updated after successful smoke tests in production
   - Used by rollback script to restore working deployments
   - Represents the last confirmed working version

### CI/CD Workflow

```
1. Build Stage:
   - Builds Docker images for app and nginx
   - Tags images with both sha-<commit> AND latest
   - Pushes to Artifact Registry

2. Deploy Stage:
   - Deploys SHA-tagged images (exact tested code)
   - Sets APP_TAG=sha-<commit> and NGINX_TAG=sha-<commit>

3. Smoke Test Stage:
   - Runs smoke tests against production
   - If successful: Promotes sha-<commit> → prod-lkg
   - If failed: Rolls back to prod-lkg
```

### Why SHA Tags for Deployment?

Using SHA tags ensures:
- **Traceability**: Know exactly which commit is deployed
- **Safety**: Deploy the exact images that were tested, not newer untested builds
- **Consistency**: No race conditions if multiple commits are pushed quickly

The `latest` tag is still created for convenience and backwards compatibility, but production deployments use SHA tags.

## Scripts

- `01-setup-gcp.sh` - Initial GCP project setup (one-time)
- `02-setup-server.sh` - Configure VM after creation (one-time)
- `03-build-push.sh` - Build and push images locally
- `04-deploy.sh` - Deploy to production VM
- `05-populate-db.sh` - Import database dump
- `06-upload-content.sh` - Upload content files
- `rollback.sh` - Rollback to last-known-good deployment

## Manual Deployment

To manually deploy a specific version:

```bash
# Deploy a specific commit
export APP_TAG=sha-abc123f
export NGINX_TAG=sha-abc123f
./04-deploy.sh

# Deploy latest (not recommended for production)
./04-deploy.sh  # Uses latest by default
```

## Rollback

The rollback script always uses `prod-lkg` tags:

```bash
./rollback.sh           # Rollback all services
./rollback.sh app       # Rollback only app
./rollback.sh nginx     # Rollback only nginx
```

## Configuration

- `compose.gcp.yaml` - Production docker-compose configuration
- `.env.production.example` - Environment variables template
