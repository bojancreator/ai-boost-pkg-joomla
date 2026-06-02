<?php
/**
 * AI Boost — Media Controller
 * Provides a lightweight AJAX API for the custom File Browser modal.
 *
 * Tasks:
 *   media.list   — list files/folders in a directory
 *   media.upload — upload a file to a directory
 *   media.mkdir  — create a new subfolder
 *   media.delete — delete a file or folder (recursive)
 *
 * Security:
 *   - Joomla CSRF token required on every POST
 *   - Admin session required (checked via parent ACL)
 *   - Path traversal protection (no ../ allowed)
 *   - Whitelist of allowed file extensions
 *   - Upload: max file size, MIME sniff check
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class MediaController extends BaseController
{
    /**
     * Map task=media.list → listFiles() and task=media.upload → upload().
     * Joomla's BaseController dispatches tasks by public method name.
     * registerTask() maps the URL-facing name to the actual method name.
     */
    public function __construct(array $config = [], $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
        $this->registerTask('list',   'listFiles');
        $this->registerTask('upload', 'upload');
        $this->registerTask('mkdir',  'makeDir');
        $this->registerTask('delete', 'deleteItem');
    }

    /** Allowed image extensions (lowercase). */
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /** Allowed document extensions. */
    private const DOC_EXT = ['pdf', 'zip'];

    /** All allowed upload extensions combined. */
    private const UPLOAD_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /** Max upload size in bytes (5 MB default). Public so HtmlView can pass it to JS. */
    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    /** Joomla site root images directory. */
    private function imagesRoot(): string
    {
        return rtrim(JPATH_SITE, '/') . '/images';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public task: media.list
    // ─────────────────────────────────────────────────────────────────────

    public function listFiles(): void
    {
        // GET request — no CSRF needed, but admin session is required.
        $this->checkAdmin();

        $rawFolder  = $this->input->getString('folder', 'images');
        $filterType = $this->input->getString('type', 'images'); // images | docs | all

        $absFolder = $this->resolveFolder($rawFolder);
        if ($absFolder === null) {
            $this->jsonError('Invalid folder path.');
            return;
        }

        $relFolder = $this->absToRel($absFolder);
        $items     = [];
        $dirs      = [];

        if (!is_dir($absFolder)) {
            $this->jsonError('Folder not found: ' . $relFolder);
            return;
        }

        $allowedExt = $filterType === 'docs'
            ? self::DOC_EXT
            : ($filterType === 'all' ? array_merge(self::IMAGE_EXT, self::DOC_EXT) : self::IMAGE_EXT);

        foreach ((array) @scandir($absFolder) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $absPath = $absFolder . '/' . $entry;
            if (is_dir($absPath)) {
                $dirs[] = [
                    'name' => $entry,
                    'path' => $relFolder . '/' . $entry,
                    'type' => 'dir',
                ];
                continue;
            }
            if (!is_file($absPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $isImage = in_array($ext, self::IMAGE_EXT, true);
            $size    = @filesize($absPath);
            $mtime   = @filemtime($absPath);
            $url     = $this->absToUrl($absPath);

            $item = [
                'name'     => $entry,
                'url'      => $url,
                'path'     => $relFolder . '/' . $entry,
                'ext'      => $ext,
                'size'     => $size,
                'size_fmt' => $this->formatSize((int) $size),
                'mtime'    => $mtime,
                'is_image' => $isImage,
                'width'    => 0,
                'height'   => 0,
            ];

            if ($isImage && $ext !== 'svg') {
                $dim = @getimagesize($absPath);
                if ($dim) {
                    $item['width']  = $dim[0];
                    $item['height'] = $dim[1];
                }
            }

            $items[] = $item;
        }

        // Sort: dirs first, then files alphabetically
        usort($items, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        $breadcrumb = $this->buildBreadcrumb($relFolder);

        echo json_encode([
            'success'    => true,
            'folder'     => $relFolder,
            'breadcrumb' => $breadcrumb,
            'dirs'       => $dirs,
            'files'      => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Factory::getApplication()->close();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public task: media.upload
    // ─────────────────────────────────────────────────────────────────────

    public function upload(): void
    {
        if (!Session::checkToken('request')) {
            $this->jsonError('Invalid security token.');
            return;
        }
        $this->checkAdmin();

        $rawFolder = $this->input->getString('folder', 'images');
        $absFolder = $this->resolveFolder($rawFolder);
        if ($absFolder === null) {
            $this->jsonError('Invalid folder path.');
            return;
        }

        if (!is_dir($absFolder) && !@mkdir($absFolder, 0755, true)) {
            $this->jsonError('Cannot create upload directory.');
            return;
        }

        $files = $this->input->files->get('file', [], 'array');
        if (empty($files) || empty($files['tmp_name'])) {
            $this->jsonError('No file received.');
            return;
        }

        $originalName = (string) ($files['name'] ?? 'upload');
        $tmpPath      = (string) ($files['tmp_name'] ?? '');
        $fileSize     = (int) ($files['size'] ?? 0);
        $error        = (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            $this->jsonError('Upload error code: ' . $error);
            return;
        }
        if ($fileSize > self::MAX_UPLOAD_BYTES) {
            $this->jsonError('File too large (max ' . $this->formatSize(self::MAX_UPLOAD_BYTES) . ').');
            return;
        }
        if (!is_uploaded_file($tmpPath)) {
            $this->jsonError('Security violation: not an uploaded file.');
            return;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::UPLOAD_EXT, true)) {
            $this->jsonError('File type .' . $ext . ' is not allowed.');
            return;
        }

        // MIME sniff check for images
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmpPath);
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (!in_array($mime, $allowedMime, true)) {
                $this->jsonError('File content does not match its extension.');
                return;
            }
        }

        // Sanitise filename: keep alphanumeric, dash, underscore, dot
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $safeName = trim($safeName, '-') ?: 'upload';
        $dest     = $absFolder . '/' . $safeName . '.' . $ext;

        // Avoid overwriting — append counter
        if (file_exists($dest)) {
            $counter = 1;
            do {
                $dest = $absFolder . '/' . $safeName . '-' . $counter . '.' . $ext;
                $counter++;
            } while (file_exists($dest) && $counter < 999);
        }

        if (!@move_uploaded_file($tmpPath, $dest)) {
            $this->jsonError('Failed to move uploaded file.');
            return;
        }

        @chmod($dest, 0644);

        $url     = $this->absToUrl($dest);
        $relPath = $this->absToRel($absFolder) . '/' . basename($dest);

        echo json_encode([
            'success' => true,
            'url'     => $url,
            'path'    => $relPath,
            'name'    => basename($dest),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Factory::getApplication()->close();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public task: media.mkdir
    // ─────────────────────────────────────────────────────────────────────

    public function makeDir(): void
    {
        if (!Session::checkToken('request')) {
            $this->jsonError('Invalid security token.');
            return;
        }
        $this->checkAdmin();

        $rawFolder = $this->input->getString('folder', 'images');
        $newName   = trim($this->input->getString('name', ''));

        // Validate folder name: letters, numbers, hyphens, underscores only
        if ($newName === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $newName)) {
            $this->jsonError('Invalid folder name. Use only letters, numbers, hyphens and underscores.');
            return;
        }

        $absFolder = $this->resolveFolder($rawFolder);
        if ($absFolder === null) {
            $this->jsonError('Invalid parent folder path.');
            return;
        }

        if (!is_dir($absFolder)) {
            $this->jsonError('Parent folder does not exist.');
            return;
        }

        $newDir = $absFolder . '/' . $newName;

        if (is_dir($newDir)) {
            $this->jsonError('A folder with that name already exists.');
            return;
        }

        if (!@mkdir($newDir, 0755)) {
            $this->jsonError('Failed to create folder.');
            return;
        }

        $relNew = $this->absToRel($newDir);

        echo json_encode([
            'success' => true,
            'name'    => $newName,
            'path'    => $relNew,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Factory::getApplication()->close();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public task: media.delete
    // ─────────────────────────────────────────────────────────────────────

    public function deleteItem(): void
    {
        if (!Session::checkToken('request')) {
            $this->jsonError('Invalid security token.');
            return;
        }
        $this->checkAdmin();

        $rawPath = $this->input->getString('path', '');
        $absPath = $this->resolveItemPath($rawPath);

        if ($absPath === null) {
            $this->jsonError('Invalid path.');
            return;
        }

        if (is_dir($absPath)) {
            if (!$this->deleteDir($absPath)) {
                $this->jsonError('Failed to delete folder. It may be in use or you may lack permissions.');
                return;
            }
        } elseif (is_file($absPath)) {
            if (!@unlink($absPath)) {
                $this->jsonError('Failed to delete file.');
                return;
            }
        } else {
            $this->jsonError('Item not found.');
            return;
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        Factory::getApplication()->close();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve a relative item path (file or folder) to an absolute path under JPATH_SITE/images.
     * Returns null if invalid, traversal detected, or item does not exist.
     * Also refuses to return the images root itself.
     */
    private function resolveItemPath(string $raw): ?string
    {
        $rel = trim($raw, '/');
        $rel = preg_replace('#/+#', '/', $rel);

        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }

        // Must be inside images/
        if ($rel !== 'images' && !str_starts_with($rel, 'images/')) {
            return null;
        }

        $root = rtrim(JPATH_SITE, '/');
        $abs  = $root . '/' . $rel;

        $realRoot = realpath($root) ?: $root;
        $realAbs  = realpath($abs);

        // Item must actually exist
        if ($realAbs === false) {
            return null;
        }

        // Must be inside JPATH_SITE/images (not the root itself)
        $imagesRoot = $realRoot . DIRECTORY_SEPARATOR . 'images';
        if (!str_starts_with($realAbs, $imagesRoot . DIRECTORY_SEPARATOR)
            && $realAbs !== $imagesRoot) {
            return null;
        }

        // Refuse to delete the images root directory itself
        if ($realAbs === $imagesRoot) {
            return null;
        }

        return $realAbs;
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * Security rules enforced on every child entry:
     *  1. Symlinks are treated as leaf nodes and unlinked — never recursed into.
     *  2. Each real child path is verified to remain inside $imagesRoot before
     *     any deletion, aborting the whole operation if an escape is detected.
     *
     * @param string $dir        Absolute path of the directory to delete.
     * @param string $imagesRoot Canonical absolute path of JPATH_SITE/images.
     */
    private function deleteDir(string $dir, string $imagesRoot = ''): bool
    {
        // Initialise imagesRoot on the first (top-level) call
        if ($imagesRoot === '') {
            $root        = rtrim(JPATH_SITE, '/');
            $imagesRoot  = (realpath($root) ?: $root) . DIRECTORY_SEPARATOR . 'images';
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            // Treat symlinks as leaf nodes — unlink without following
            if (is_link($path)) {
                if (!@unlink($path)) {
                    return false;
                }
                continue;
            }

            // Re-validate real path to guarantee we stay inside images/
            $realChild = realpath($path);
            if ($realChild === false
                || (!str_starts_with($realChild, $imagesRoot . DIRECTORY_SEPARATOR)
                    && $realChild !== $imagesRoot)
            ) {
                // Path escapes images root — abort
                return false;
            }

            if (is_dir($path)) {
                if (!$this->deleteDir($path, $imagesRoot)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
    }

    /**
     * Resolve a relative folder path to an absolute path under JPATH_SITE/images.
     * Returns null if path traversal or escape is detected.
     */
    private function resolveFolder(string $raw): ?string
    {
        // Normalise: strip leading slash, collapse slashes
        $rel = trim($raw, '/');
        $rel = preg_replace('#/+#', '/', $rel);

        // Reject traversal attempts
        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }

        // Must be inside images root
        $root = rtrim(JPATH_SITE, '/');
        $abs  = $root . '/' . ($rel ?: 'images');

        // Realpath to resolve any symlinks; allow even if dir does not exist yet (upload)
        $realRoot = realpath($root) ?: $root;
        $realAbs  = realpath($abs);
        if ($realAbs !== false && !str_starts_with($realAbs, $realRoot . '/')) {
            return null;
        }

        // Require path to start with images/
        if ($rel !== 'images' && !str_starts_with($rel, 'images/')) {
            // Default to images root
            return $root . '/images';
        }

        return $abs;
    }

    /** Convert absolute path to site-relative URL. */
    private function absToUrl(string $abs): string
    {
        // Return a root-relative URL (/images/...) so it works in any
        // Joomla admin context without cross-origin or path-mismatch issues.
        $root = rtrim(JPATH_SITE, DIRECTORY_SEPARATOR);
        $rel  = str_replace([$root . DIRECTORY_SEPARATOR, $root . '/'], '', $abs);
        $rel  = ltrim(str_replace('\\', '/', $rel), '/');
        return '/' . $rel;
    }

    /** Convert absolute path to relative path from JPATH_SITE. */
    private function absToRel(string $abs): string
    {
        $root = rtrim(JPATH_SITE, '/');
        return ltrim(str_replace($root, '', $abs), '/');
    }

    private function buildBreadcrumb(string $relFolder): array
    {
        $crumbs = [];
        $parts  = array_filter(explode('/', $relFolder), fn($p) => $p !== '');
        $path   = '';
        foreach ($parts as $part) {
            $path   .= ($path ? '/' : '') . $part;
            $crumbs[] = ['label' => $part, 'path' => $path];
        }
        return $crumbs;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    private function checkAdmin(): void
    {
        $user = Factory::getApplication()->getIdentity();
        // core.manage on com_aiboost is more specific than core.login.admin
        if (!$user || (!$user->authorise('core.manage', 'com_aiboost') && !$user->authorise('core.admin'))) {
            $this->jsonError('Unauthorized.', 403);
        }
    }

    private function jsonError(string $msg, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $msg]);
        Factory::getApplication()->close();
    }
}
