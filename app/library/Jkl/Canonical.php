<?php

/**
 * Helper class for canonical URL redirects
 * Ensures SEO-friendly single URL per page by redirecting non-canonical URLs
 */
class Jkl_Canonical
{
    /**
     * Check if current URL matches canonical URL and redirect if needed
     *
     * @param Zend_Controller_Action $controller The controller instance
     * @param string $canonicalSlug The canonical URL slug (without domain)
     * @return void Issues 301 redirect if URL doesn't match
     */
    public static function redirectIfNeeded(Zend_Controller_Action $controller, string $canonicalSlug): void
    {
        $request = $controller->getRequest();
        $currentPath = $request->getRequestUri();

        // Remove query string if present
        $currentPath = strtok($currentPath, '?');

        // Extract just the slug part (after last slash) from both current and canonical
        $currentSlug = basename($currentPath);
        $canonicalBasename = basename($canonicalSlug);

        // If current slug doesn't match canonical basename, do 301 redirect
        if ($currentSlug !== $canonicalBasename) {
            $controller->getHelper('redirector')
                ->gotoUrl('/' . $canonicalSlug, ['code' => 301]);
        }
    }

    /**
     * Generate canonical meta tag
     *
     * @param string $canonicalUrl Full canonical URL including domain
     * @return string HTML meta tag
     */
    public static function getMetaTag(string $canonicalUrl): string
    {
        return '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />';
    }
}
