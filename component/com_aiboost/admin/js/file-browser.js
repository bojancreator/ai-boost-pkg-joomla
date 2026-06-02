/**
 * AI Boost — Custom File Browser
 * Lightweight modal media picker for com_aiboost Settings.
 *
 * Usage:
 *   All inputs with [data-aiboost-browser] get a "Browse" button injected.
 *   Clicking Browse opens the modal. Selecting a file writes the URL to the
 *   source input and updates the thumbnail preview.
 *
 * API:
 *   GET  index.php?option=com_aiboost&task=media.list&folder=images[/sub]&type=images
 *   POST index.php?option=com_aiboost&task=media.upload  (multipart/form-data)
 *   POST index.php?option=com_aiboost&task=media.mkdir   {folder, name, token}
 *   POST index.php?option=com_aiboost&task=media.delete  {path, token}
 *
 * @package AiBoost
 */
(function () {
    'use strict';

    /* ── State ───────────────────────────────────────────────── */
    var modal       = null;
    var targetInput = null;  // the input field that triggered Browse
    var currentFolder = 'images';
    var allFiles    = [];    // flat list of files in current folder
    var allDirs     = [];    // subdirectories in current folder
    var tokenName   = '';
    var tokenVal    = '1';
    var baseUrl     = 'index.php?option=com_aiboost&format=json';

    /* ── Bootstrap ──────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        tokenName = (window.AiBoostFileBrowser && window.AiBoostFileBrowser.tokenName) || '';
        createModal();
        initBrowseButtons();
    });

    /* ═══════════════════════════════════════════════════════════
       MODAL CREATION
    ═══════════════════════════════════════════════════════════ */
    function createModal() {
        var div = document.createElement('div');
        div.id  = 'ab-fb-modal';
        div.className = 'ab-fb-overlay';
        div.style.display = 'none';
        div.setAttribute('role', 'dialog');
        div.setAttribute('aria-modal', 'true');
        div.setAttribute('aria-label', 'AI Boost File Browser');

        div.innerHTML = [
          '<div class="ab-fb-dialog">',

            /* ── Header ── */
            '<div class="ab-fb-header">',
              '<span class="ab-fb-title">',
                '<span class="icon-images me-1" aria-hidden="true"></span>',
                'AI Boost — File Browser',
              '</span>',
              '<button id="ab-fb-close" class="ab-fb-close-btn" aria-label="Close">&times;</button>',
            '</div>',

            /* ── Toolbar ── */
            '<div class="ab-fb-toolbar">',
              '<div class="ab-fb-toolbar-left">',
                '<button id="ab-fb-up-btn" class="btn btn-sm btn-outline-secondary me-1" title="Go up one folder" disabled>',
                  '<span class="icon-arrow-up-2" aria-hidden="true"></span>',
                  '<span class="visually-hidden">Go up</span>',
                '</button>',
                '<nav id="ab-fb-breadcrumb" class="ab-fb-breadcrumb" aria-label="Folder navigation"></nav>',
              '</div>',
              '<div class="ab-fb-toolbar-right">',
                '<input id="ab-fb-search" type="search" class="form-control form-control-sm"',
                '       placeholder="Search files…" aria-label="Search files" style="width:180px;">',
                '<button id="ab-fb-mkdir-btn" class="btn btn-sm btn-outline-success ms-2" title="Create new folder">',
                  '<span class="icon-folder-2 me-1" aria-hidden="true"></span>New folder',
                '</button>',
                '<button id="ab-fb-upload-btn" class="btn btn-sm btn-outline-primary ms-2">',
                  '<span class="icon-upload me-1" aria-hidden="true"></span>Upload',
                '</button>',
              '</div>',
            '</div>',

            /* ── Body ── */
            '<div class="ab-fb-body">',
              /* Upload drop zone (hidden until upload btn clicked) */
              '<div id="ab-fb-dropzone" class="ab-fb-dropzone d-none">',
                '<div class="ab-fb-dropzone-inner">',
                  '<span class="icon-upload" style="font-size:2rem;" aria-hidden="true"></span>',
                  '<p class="mb-1 mt-2">Drag &amp; drop image here, or click to browse</p>',
                  '<p class="text-muted small mb-2">JPG, PNG, GIF, WebP, SVG — max 5 MB</p>',
                  '<input id="ab-fb-file-input" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"',
                  '       style="display:none;">',
                  '<button id="ab-fb-choose-file" class="btn btn-sm btn-secondary">Choose file</button>',
                  '<div id="ab-fb-progress-wrap" class="ab-fb-progress-wrap d-none">',
                    '<div class="progress mt-2" style="height:6px;">',
                      '<div id="ab-fb-progress" class="progress-bar" role="progressbar"',
                      '     style="width:0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>',
                    '</div>',
                    '<p id="ab-fb-upload-msg" class="small mt-1 mb-0"></p>',
                  '</div>',
                '</div>',
              '</div>',

              /* Grid */
              '<div id="ab-fb-grid" class="ab-fb-grid"></div>',
            '</div>',

            /* ── Footer ── */
            '<div class="ab-fb-footer">',
              '<div class="ab-fb-footer-left">',
                '<label class="form-label-sm text-muted mb-0 me-1" style="font-size:.8rem;">Or enter URL:</label>',
                '<input id="ab-fb-direct-url" type="text" class="form-control form-control-sm"',
                '       placeholder="https://cdn.example.com/image.jpg" style="width:320px;">',
                '<button id="ab-fb-use-url" class="btn btn-sm btn-outline-secondary ms-1">Use URL</button>',
              '</div>',
              '<div>',
                '<button id="ab-fb-cancel" class="btn btn-sm btn-secondary">Cancel</button>',
              '</div>',
            '</div>',

          '</div>',
        ].join('');

        document.body.appendChild(div);
        modal = div;

        /* Close handlers */
        document.getElementById('ab-fb-close').addEventListener('click', closeModal);
        document.getElementById('ab-fb-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) { closeModal(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') { closeModal(); }
        });

        /* Search */
        document.getElementById('ab-fb-search').addEventListener('input', function () {
            renderGrid(this.value.trim().toLowerCase());
        });

        /* Direct URL */
        document.getElementById('ab-fb-use-url').addEventListener('click', function () {
            var url = document.getElementById('ab-fb-direct-url').value.trim();
            if (url) { selectFile(url); }
        });

        /* Go up button */
        document.getElementById('ab-fb-up-btn').addEventListener('click', function () {
            var parts = currentFolder.split('/').filter(Boolean);
            if (parts.length <= 1) { return; } // already at images root
            parts.pop();
            var parent = parts.join('/');
            var ft = modal.getAttribute('data-filter-type') || 'images';
            loadFolder(parent, ft);
        });

        /* New folder btn */
        document.getElementById('ab-fb-mkdir-btn').addEventListener('click', promptNewFolder);

        /* Upload btn toggle */
        document.getElementById('ab-fb-upload-btn').addEventListener('click', toggleDropzone);

        /* Dropzone */
        var dz = document.getElementById('ab-fb-dropzone');
        dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', function () { dz.classList.remove('dragover'); });
        dz.addEventListener('drop', function (e) {
            e.preventDefault();
            dz.classList.remove('dragover');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files[0]) { uploadFile(files[0]); }
        });

        var fileInput = document.getElementById('ab-fb-file-input');
        document.getElementById('ab-fb-choose-file').addEventListener('click', function () {
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) { uploadFile(this.files[0]); }
        });
    }

    /* ═══════════════════════════════════════════════════════════
       BROWSE BUTTON INJECTION
    ═══════════════════════════════════════════════════════════ */
    function initBrowseButtons() {
        document.querySelectorAll('[data-aiboost-browser]').forEach(function (input) {
            var wrap = document.createElement('div');
            wrap.className = 'ab-img-input-wrap';

            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary ab-browse-btn';
            btn.innerHTML = '<span class="icon-images me-1" aria-hidden="true"></span>Browse';
            wrap.appendChild(btn);

            var filterType = input.getAttribute('data-aiboost-browser') || 'images';

            btn.addEventListener('click', function () {
                openModal(input, filterType);
            });

            /* Thumbnail preview under the input group */
            updatePreview(input);
            input.addEventListener('change', function () { updatePreview(input); });
            input.addEventListener('input',  function () { updatePreview(input); });
        });
    }

    /* ═══════════════════════════════════════════════════════════
       OPEN / CLOSE
    ═══════════════════════════════════════════════════════════ */
    function positionDialog() {
        var dlg = modal.querySelector('.ab-fb-dialog');
        if (!dlg) { return; }
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var w  = Math.min(1060, Math.round(vw * 0.98));
        var h  = Math.min(740,  Math.round(vh * 0.95));
        dlg.style.width  = w + 'px';
        dlg.style.height = h + 'px';
        dlg.style.left   = Math.round((vw - w) / 2) + 'px';
        dlg.style.top    = Math.round((vh - h) / 2) + 'px';
    }

    function openModal(input, filterType) {
        targetInput = input;
        modal.setAttribute('data-filter-type', filterType || 'images');
        modal.style.display = 'block';
        positionDialog();
        window.addEventListener('resize', positionDialog);
        document.getElementById('ab-fb-search').value     = '';
        document.getElementById('ab-fb-direct-url').value = '';
        hideDropzone();
        loadFolder('images', filterType || 'images');
        setTimeout(function () {
            document.getElementById('ab-fb-search').focus();
        }, 100);
    }

    function closeModal() {
        modal.style.display = 'none';
        window.removeEventListener('resize', positionDialog);
        targetInput = null;
    }

    /* ═══════════════════════════════════════════════════════════
       FOLDER NAVIGATION
    ═══════════════════════════════════════════════════════════ */
    function loadFolder(folder, filterType) {
        currentFolder = folder;
        var grid      = document.getElementById('ab-fb-grid');
        grid.innerHTML = '<div class="ab-fb-loading"><span class="icon-spinner spin me-1" aria-hidden="true"></span>Loading…</div>';

        var ft = filterType || modal.getAttribute('data-filter-type') || 'images';
        var url = baseUrl + '&task=media.list'
                + '&folder=' + encodeURIComponent(folder)
                + '&type='   + encodeURIComponent(ft);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    grid.innerHTML = '<p class="text-danger p-3">' + esc(data.message || 'Error loading folder.') + '</p>';
                    return;
                }
                allFiles = data.files || [];
                allDirs  = data.dirs  || [];
                renderBreadcrumb(data.breadcrumb || []);
                /* Enable/disable Go up button depending on depth */
                var upBtn = document.getElementById('ab-fb-up-btn');
                if (upBtn) {
                    var depth = (data.folder || '').split('/').filter(Boolean).length;
                    upBtn.disabled = depth <= 1;
                }
                renderGrid('');
            })
            .catch(function (e) {
                grid.innerHTML = '<p class="text-danger p-3">Network error: ' + esc(String(e)) + '</p>';
            });
    }

    /* ═══════════════════════════════════════════════════════════
       BREADCRUMB
    ═══════════════════════════════════════════════════════════ */
    function renderBreadcrumb(crumbs) {
        var bc = document.getElementById('ab-fb-breadcrumb');
        var ft = modal.getAttribute('data-filter-type') || 'images';

        var html = '<button class="ab-fb-crumb ab-fb-crumb-root" data-path="images" data-ft="' + esc(ft) + '">'
                 + '<span class="icon-home me-1" aria-hidden="true"></span>images</button>';

        crumbs.forEach(function (c, i) {
            if (c.path === 'images') { return; } // root already shown
            html += '<span class="ab-fb-crumb-sep">/</span>';
            if (i === crumbs.length - 1) {
                html += '<span class="ab-fb-crumb active">' + esc(c.label) + '</span>';
            } else {
                html += '<button class="ab-fb-crumb" data-path="' + esc(c.path) + '" data-ft="' + esc(ft) + '">'
                      + esc(c.label) + '</button>';
            }
        });

        bc.innerHTML = html;
        bc.querySelectorAll('[data-path]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                loadFolder(this.getAttribute('data-path'), this.getAttribute('data-ft'));
            });
        });
    }

    /* ═══════════════════════════════════════════════════════════
       GRID RENDER
    ═══════════════════════════════════════════════════════════ */
    function renderGrid(query) {
        var grid = document.getElementById('ab-fb-grid');
        var ft   = modal.getAttribute('data-filter-type') || 'images';
        var html = '';

        /* Subdirectories */
        var matchedDirs = allDirs.filter(function (d) {
            return !query || d.name.toLowerCase().indexOf(query) !== -1;
        });
        matchedDirs.forEach(function (d) {
            html += '<div class="ab-fb-item ab-fb-item-dir" data-path="' + esc(d.path) + '" data-ft="' + esc(ft) + '">'
                  + '<button class="ab-fb-item-del" data-del-path="' + esc(d.path) + '" data-del-name="' + esc(d.name) + '" data-del-type="dir" title="Delete folder" aria-label="Delete folder">&times;</button>'
                  + '<div class="ab-fb-thumb ab-fb-thumb-dir"><span class="icon-folder" aria-hidden="true"></span></div>'
                  + '<div class="ab-fb-item-name" title="' + esc(d.name) + '">' + esc(d.name) + '</div>'
                  + '</div>';
        });

        /* Files */
        var matchedFiles = allFiles.filter(function (f) {
            return !query || f.name.toLowerCase().indexOf(query) !== -1;
        });
        matchedFiles.forEach(function (f) {
            var thumb = f.is_image
                ? '<img src="' + esc(f.url) + '" alt="" class="ab-fb-thumb-img" loading="lazy">'
                : '<span class="icon-file ab-fb-thumb-icon" aria-hidden="true"></span>';
            var dim = (f.width && f.height) ? ' ' + f.width + '×' + f.height : '';
            html += '<div class="ab-fb-item" data-url="' + esc(f.url) + '" title="' + esc(f.name) + dim + '\n' + f.size_fmt + '">'
                  + '<button class="ab-fb-item-del" data-del-path="' + esc(f.path) + '" data-del-name="' + esc(f.name) + '" data-del-type="file" title="Delete file" aria-label="Delete file">&times;</button>'
                  + '<div class="ab-fb-thumb">' + thumb + '</div>'
                  + '<div class="ab-fb-item-name">' + esc(f.name) + '</div>'
                  + '<div class="ab-fb-item-meta">' + f.size_fmt + (dim ? ' · ' + dim.trim() : '') + '</div>'
                  + '</div>';
        });

        if (!html) {
            html = '<div class="ab-fb-empty">'
                 + (query ? 'No files match "<strong>' + esc(query) + '</strong>".' : 'This folder is empty.')
                 + '</div>';
        }

        grid.innerHTML = html;

        /* Dir click → navigate (skip if delete btn was clicked) */
        grid.querySelectorAll('.ab-fb-item-dir').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('.ab-fb-item-del')) { return; }
                loadFolder(this.getAttribute('data-path'), this.getAttribute('data-ft'));
            });
        });

        /* File click → select (skip if delete btn was clicked) */
        grid.querySelectorAll('.ab-fb-item[data-url]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('.ab-fb-item-del')) { return; }
                selectFile(this.getAttribute('data-url'));
            });
        });

        /* Delete button click */
        grid.querySelectorAll('.ab-fb-item-del').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var path = this.getAttribute('data-del-path');
                var name = this.getAttribute('data-del-name');
                var type = this.getAttribute('data-del-type');
                var msg  = type === 'dir'
                    ? 'Delete folder "' + name + '" and ALL its contents?\n\nThis cannot be undone.'
                    : 'Delete file "' + name + '"?\n\nThis cannot be undone.';
                if (window.confirm(msg)) {
                    deleteItem(path);
                }
            });
        });
    }

    /* ═══════════════════════════════════════════════════════════
       NEW FOLDER
    ═══════════════════════════════════════════════════════════ */
    function promptNewFolder() {
        var name = window.prompt('New folder name (letters, numbers, hyphens and underscores only):');
        if (name === null) { return; } // cancelled
        name = name.trim();
        if (!name) { return; }
        if (!/^[a-zA-Z0-9_-]+$/.test(name)) {
            window.alert('Invalid folder name.\nUse only letters, numbers, hyphens and underscores.');
            return;
        }
        createFolder(currentFolder, name);
    }

    function createFolder(folder, name) {
        var fd = new FormData();
        fd.append('folder', folder);
        fd.append('name', name);
        if (tokenName) { fd.append(tokenName, tokenVal); }

        fetch(baseUrl + '&task=media.mkdir', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                loadFolder(currentFolder);
            } else {
                window.alert('Could not create folder: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(function () {
            window.alert('Network error while creating folder.');
        });
    }

    /* ═══════════════════════════════════════════════════════════
       DELETE ITEM
    ═══════════════════════════════════════════════════════════ */
    function deleteItem(path) {
        var fd = new FormData();
        fd.append('path', path);
        if (tokenName) { fd.append(tokenName, tokenVal); }

        fetch(baseUrl + '&task=media.delete', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                loadFolder(currentFolder);
            } else {
                window.alert('Could not delete item: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(function () {
            window.alert('Network error while deleting item.');
        });
    }

    /* ═══════════════════════════════════════════════════════════
       SELECT FILE
    ═══════════════════════════════════════════════════════════ */
    function selectFile(url) {
        if (!targetInput) { return; }
        targetInput.value = url;
        targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        targetInput.dispatchEvent(new Event('input',  { bubbles: true }));
        updatePreview(targetInput);
        closeModal();
    }

    /* ═══════════════════════════════════════════════════════════
       THUMBNAIL PREVIEW
    ═══════════════════════════════════════════════════════════ */
    function updatePreview(input) {
        var url   = (input.value || '').trim();
        var previewId = input.getAttribute('data-preview-id');
        var preview   = previewId ? document.getElementById(previewId) : null;

        if (!preview) {
            /* Try generic sibling preview element */
            var wrap = input.closest('.ab-img-input-group') || input.parentNode;
            preview  = wrap && wrap.querySelector('.ab-img-preview');
        }

        if (!preview) { return; }

        if (url) {
            preview.src   = url;
            preview.style.display = '';
            var wrapEl = preview.closest('.ab-img-preview-wrap');
            if (wrapEl) { wrapEl.style.display = ''; }
        } else {
            preview.style.display = 'none';
            var wrapEl2 = preview.closest('.ab-img-preview-wrap');
            if (wrapEl2) { wrapEl2.style.display = 'none'; }
        }
    }

    /* ═══════════════════════════════════════════════════════════
       UPLOAD
    ═══════════════════════════════════════════════════════════ */
    function toggleDropzone() {
        var dz  = document.getElementById('ab-fb-dropzone');
        var btn = document.getElementById('ab-fb-upload-btn');
        if (dz.classList.contains('d-none')) {
            dz.classList.remove('d-none');
            btn.innerHTML = '<span class="icon-times me-1" aria-hidden="true"></span>Hide upload';
        } else {
            hideDropzone();
        }
    }

    function hideDropzone() {
        var dz  = document.getElementById('ab-fb-dropzone');
        var btn = document.getElementById('ab-fb-upload-btn');
        if (dz) {
            dz.classList.add('d-none');
            dz.classList.remove('dragover');
        }
        if (btn) {
            btn.innerHTML = '<span class="icon-upload me-1" aria-hidden="true"></span>Upload';
        }
        var pw = document.getElementById('ab-fb-progress-wrap');
        if (pw) { pw.classList.add('d-none'); }
    }

    function uploadFile(file) {
        var MAX     = (window.AiBoostFileBrowser && window.AiBoostFileBrowser.maxUploadBytes) || (5 * 1024 * 1024);
        var maxMb   = Math.round(MAX / (1024 * 1024));
        var allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (file.size > MAX) {
            showUploadMsg('File too large (max ' + maxMb + ' MB).', 'error');
            return;
        }
        if (!allowed.includes(file.type)) {
            showUploadMsg('File type not allowed: ' + file.type, 'error');
            return;
        }

        var pw  = document.getElementById('ab-fb-progress-wrap');
        var bar = document.getElementById('ab-fb-progress');
        pw.classList.remove('d-none');
        setProgress(0);
        showUploadMsg('Uploading…', 'info');

        var fd = new FormData();
        fd.append('file', file);
        fd.append('folder', currentFolder);
        if (tokenName) { fd.append(tokenName, tokenVal); }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', baseUrl + '&task=media.upload');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) { setProgress(Math.round(e.loaded / e.total * 100)); }
        });

        xhr.addEventListener('load', function () {
            setProgress(100);
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    showUploadMsg('Upload successful!', 'success');
                    /* Reload folder and auto-select the new file */
                    loadFolder(currentFolder);
                    setTimeout(function () { selectFile(res.url); }, 600);
                } else {
                    showUploadMsg(res.message || 'Upload failed.', 'error');
                }
            } catch (e) {
                showUploadMsg('Server error during upload.', 'error');
            }
        });

        xhr.addEventListener('error', function () {
            showUploadMsg('Network error during upload.', 'error');
        });

        xhr.send(fd);
    }

    function setProgress(pct) {
        var bar = document.getElementById('ab-fb-progress');
        if (bar) {
            bar.style.width      = pct + '%';
            bar.setAttribute('aria-valuenow', String(pct));
        }
    }

    function showUploadMsg(msg, type) {
        var el = document.getElementById('ab-fb-upload-msg');
        if (!el) { return; }
        el.textContent = msg;
        el.style.color = type === 'error' ? 'var(--danger, #dc3545)'
                       : type === 'success' ? 'var(--success, #198754)'
                       : '';
    }

    /* ═══════════════════════════════════════════════════════════
       UTILITIES
    ═══════════════════════════════════════════════════════════ */
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;');
    }

}());
