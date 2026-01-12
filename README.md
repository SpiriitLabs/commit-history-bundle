# SpiriitLabs Commit History Bundle

A Symfony bundle that fetches commit history from GitLab or GitHub repositories and displays them in a beautiful vertical timeline UI — similar to the Symfony releases page.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-6.4%2B%7C7.0%2B-blue.svg)](https://symfony.com)
[![Latest Stable Version](https://poser.pugx.org/spiriitlabs/commit-history-bundle/v/stable.svg)](https://packagist.org/packages/spiriitlabs/commit-history-bundle)

## Features

- **Multi-Provider Support**: Fetch commits from GitLab or GitHub repositories
- **REST API Integration**: Uses official APIs with pagination to fetch all commits
- **Self-Hosted Support**: Works with GitLab self-hosted instances and GitHub Enterprise
- **Year Filtering**: Filter commits by year with a dropdown, fetched via API with per-year caching
- **Dependency Tracking**: Automatically detect and display dependency changes (composer.json, package.json, etc.)
- **Vertical Timeline UI**: Beautiful, responsive timeline inspired by Symfony releases
- **Caching**: 1-hour cache by default to reduce API calls (cached per year)
- **Private Repository Support**: Supports authentication tokens for private repositories
- **Standalone Page**: Ready-to-use page with embedded CSS
- **Embeddable Fragment**: Include the timeline in your own layouts

## Architecture

This project is split into two packages:

- **[spiriitlabs/commit-history](https://github.com/SpiriitLabs/commit-history)** - A standalone, framework-agnostic PHP library containing all the core logic (providers, DTOs, services, diff parsers)
- **spiriitlabs/commit-history-bundle** (this package) - A Symfony bundle providing controllers, commands, Twig templates, and adapters to integrate the library with Symfony

This separation allows you to use the core library in any PHP project (Laravel, plain PHP, etc.) while Symfony users get a ready-to-use bundle with full integration.

### Bundle Components

The bundle provides Symfony-specific integrations:

| Component | Description |
|-----------|-------------|
| `SymfonyCacheAdapter` | Bridges Symfony's cache to the library's `CacheInterface` |
| `SymfonyHttpClientAdapter` | Bridges Symfony's HTTP client to the library's `HttpClientInterface` |
| `TimelineController` | Renders the timeline page |
| `DependenciesChangesController` | JSON API for dependency details |
| `RefreshCacheCommand` | CLI command to refresh cache |
| `ClearCacheCommand` | CLI command to clear cache |

## Requirements

- PHP 8.2 or higher
- Symfony 6.4+ or 7.0+

## Installation

Install the bundle using Composer:

```bash
composer require spiriitlabs/commit-history-bundle
```

If you're using Symfony Flex, the bundle will be automatically registered. Otherwise, add it to your `config/bundles.php`:

```php
<?php

return [
    // ...
    Spiriit\Bundle\CommitHistoryBundle\SpiriitCommitHistoryBundle::class => ['all' => true],
];
```

## Configuration

Create a configuration file `config/packages/spiriit_commit_history.yaml`:

### GitLab Configuration

```yaml
spiriit_commit_history:
    provider: gitlab
    feed_name: "My Project"
    cache_ttl: 3600
    available_years_count: 6

    gitlab:
        project_id: "123"  # or "group%2Fproject" for path
        base_url: "https://gitlab.com"  # or your self-hosted GitLab URL
        token: "%env(GITLAB_TOKEN)%"    # optional, only for private repos
        ref: "main"                      # optional, defaults to default branch
```

### GitHub Configuration

```yaml
spiriit_commit_history:
    provider: github
    feed_name: "My Project"
    cache_ttl: 3600
    available_years_count: 6

    github:
        owner: "myorg"
        repo: "myproject"
        base_url: "https://api.github.com"  # or your GitHub Enterprise URL
        token: "%env(GITHUB_TOKEN)%"        # optional, only for private repos
        ref: "main"                          # optional, defaults to default branch
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `provider` | string | required | Provider type: `gitlab` or `github` |
| `feed_name` | string | `Commits` | Display name for the timeline |
| `cache_ttl` | integer | `3600` | Cache duration in seconds |
| `available_years_count` | integer | `6` | Number of years to show in the year filter dropdown |
| `track_dependency_changes` | boolean | `true` | Enable dependency change detection |
| `dependency_files` | array | `[composer.json, composer.lock, package.json, package-lock.json]` | Files to track for dependency changes |

#### GitLab Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `project_id` | string | required | Numeric ID or URL-encoded path |
| `base_url` | string | `https://gitlab.com` | GitLab instance URL |
| `token` | string | `null` | Personal Access Token (for private repos) |
| `ref` | string | `null` | Branch or tag name |

#### GitHub Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `owner` | string | required | Repository owner |
| `repo` | string | required | Repository name |
| `base_url` | string | `https://api.github.com` | GitHub API URL |
| `token` | string | `null` | Personal Access Token (for private repos) |
| `ref` | string | `null` | Branch or tag name |

### Routes Configuration

Add the routes to your `config/routes.yaml`:

```yaml
spiriit_commit_history:
    resource: '@SpiriitCommitHistoryBundle/Resources/config/routes.php'
    type: php
    prefix: /commits    # Customize the URL path here
```

## Usage

### Standalone Page

Visit `/commits` (or your configured `prefix`) to see the timeline. The page displays commits for the current year by default, with a dropdown to select other years.

You can also directly access a specific year by adding the `year` query parameter:

```
/commits?year=2024
```

### Embedding in Your Own Layout

You can embed the timeline in your own templates by injecting the `FeedFetcherInterface` service:

```php
<?php

namespace App\Controller;

use Spiriit\CommitHistory\Service\FeedFetcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MyController extends AbstractController
{
    public function changelog(Request $request, FeedFetcherInterface $feedFetcher): Response
    {
        // Get year from query parameter, defaults to current year
        $year = $request->query->getInt('year') ?: (int) date('Y');

        $commits = $feedFetcher->fetch($year);
        $availableYears = $feedFetcher->getAvailableYears();

        return $this->render('changelog.html.twig', [
            'commits' => $commits,
            'available_years' => $availableYears,
            'selected_year' => $year,
        ]);
    }
}
```

Then in your template:

```twig
{# templates/changelog.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Changelog</h1>
    {{ include('@SpiriitCommitHistory/_commits.html.twig', {
        commits: commits
    }) }}
{% endblock %}
```

### Console Commands

#### Refresh Cache

Refresh the commit cache and re-detect dependency badges:

```bash
# Refresh current year (default)
php bin/console spiriit:commit-history:refresh

# Refresh a specific year
php bin/console spiriit:commit-history:refresh 2024

# Refresh all available years
php bin/console spiriit:commit-history:refresh --all
php bin/console spiriit:commit-history:refresh -a
```

This command clears the commits list cache and dependency detection cache, then fetches fresh data from the provider. Each year is cached separately.

#### Clear Cache

Clear all caches (commits list, dependency detection, and dependency changes):

```bash
# Clear all caches for all years
php bin/console spiriit:commit-history:clear

# Clear caches for a specific year only
php bin/console spiriit:commit-history:clear 2024
```

This command clears all cached data without re-fetching. Useful if you want to force a complete refresh on the next page load.

## Dependency Tracking

The bundle automatically detects commits that modify dependency files (composer.json, package.json, etc.) and displays a "DEPENDENCIES" badge on these commits. Clicking the badge reveals the list of added, updated, or removed dependencies.

### How It Works

1. **Badge Detection**: When commits are loaded, the bundle checks if any dependency files were modified. This check is cached per-commit (forever, since commit content is immutable).

2. **Lazy Loading**: The dependency details are only fetched when a user clicks the badge, reducing initial page load time.

3. **Per-Commit Caching**: Both badge detection and dependency details are cached per-commit. Since commit IDs are immutable, this cache never needs to expire.

### Configuration

```yaml
spiriit_commit_history:
    # Enable or disable dependency tracking entirely (default: true)
    track_dependency_changes: true

    # Files to track for dependency changes
    dependency_files:
        - composer.json
        - composer.lock
        - package.json
        - package-lock.json
```

### Extending with Custom Parsers

The bundle uses a tagged service pattern for diff parsers. You can add support for additional dependency file formats by creating your own parser.

1. Create a parser class implementing `DiffParserInterface` from the library:

```php
<?php

namespace App\Service\DiffParser;

use Spiriit\CommitHistory\DTO\DependencyChange;
use Spiriit\CommitHistory\DiffParser\DiffParserInterface;

class GemfileDiffParser implements DiffParserInterface
{
    public function supports(string $filename): bool
    {
        return basename($filename) === 'Gemfile.lock';
    }

    /**
     * @return DependencyChange[]
     */
    public function parse(string $diff, string $filename): array
    {
        // Parse the diff and return DependencyChange objects
        $changes = [];
        // ... your parsing logic
        return $changes;
    }
}
```

2. Tag your service:

```yaml
services:
    App\Service\DiffParser\GemfileDiffParser:
        tags: ['spiriit_commit_history.diff_parser']
```

3. Add the file to the tracked files:

```yaml
spiriit_commit_history:
    dependency_files:
        - composer.json
        - composer.lock
        - package.json
        - package-lock.json
        - Gemfile.lock  # Your new file
```

## Authentication

### GitLab

For private repositories, create a Personal Access Token:

1. Go to GitLab → User Settings → Access Tokens
2. Create a token with `read_api` scope
3. Add to your `.env` file:

```env
GITLAB_TOKEN=glpat-xxxxxxxxxxxxxxxxxxxx
```

### GitHub

For private repositories, create a Personal Access Token:

1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Create a token (classic or fine-grained) with `repo` scope
3. Add to your `.env` file:

```env
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
```

## Template Customization

Override the default templates by creating files in your project:

```bash
templates/bundles/SpiriitCommitHistoryBundle/timeline.html.twig
templates/bundles/SpiriitCommitHistoryBundle/_commits.html.twig
```

### Available CSS Classes (BEM)

The templates use BEM naming convention:

| Class | Description |
|-------|-------------|
| `.timeline` | Main timeline container |
| `.timeline--empty` | Empty state modifier |
| `.timeline__filter` | Year filter form container |
| `.timeline__filter-select` | Year dropdown select |
| `.timeline__item` | Individual commit item |
| `.timeline__title` | Commit title |
| `.timeline__meta` | Metadata container |
| `.timeline__hash` | Commit hash |
| `.timeline__date` | Commit date |
| `.timeline__author` | Author name |
| `.timeline__badge` | Dependency badge (DEPENDENCIES label) |
| `.timeline__badge--loading` | Loading state for badge |
| `.timeline__dependencies` | Dependencies detail container |
| `.timeline__dependencies-list` | List of dependency changes |
| `.timeline__dependencies-item` | Individual dependency change |
| `.timeline__dependencies-name` | Package name |
| `.timeline__dependencies-version` | Version information |
| `.timeline__dependencies-type` | Change type indicator dot |
| `.timeline__dependencies-type--added` | Added dependency (green) |
| `.timeline__dependencies-type--updated` | Updated dependency (orange) |
| `.timeline__dependencies-type--removed` | Removed dependency (red) |

## Using the Standalone Library

If you're not using Symfony, you can use the standalone library directly. See the [spiriitlabs/commit-history](https://github.com/SpiriitLabs/commit-history) repository for documentation.

```bash
composer require spiriitlabs/commit-history
```

The library provides:

- `Spiriit\CommitHistory\Provider\Github\GithubProvider` - GitHub API client
- `Spiriit\CommitHistory\Provider\Gitlab\GitlabProvider` - GitLab API client
- `Spiriit\CommitHistory\Service\FeedFetcher` - Caching wrapper for providers
- `Spiriit\CommitHistory\DTO\Commit` - Commit data transfer object
- `Spiriit\CommitHistory\DTO\DependencyChange` - Dependency change DTO
- `Spiriit\CommitHistory\DiffParser\*` - Diff parsers for dependency detection

You'll need to provide your own implementations of:

- `Spiriit\CommitHistory\Contract\CacheInterface` - Cache abstraction
- `Spiriit\CommitHistory\Contract\HttpClientInterface` - HTTP client abstraction

## Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

For questions and support, please open an issue on GitHub or contact [dev@spiriit.com](mailto:dev@spiriit.com).
