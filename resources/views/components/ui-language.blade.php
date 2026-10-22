<script>
    (() => {
        if (window.__spjUiLanguageReady) return;
        window.__spjUiLanguageReady = true;

        const exact = new Map([
            ['Dashboard Operasional', 'Ringkasan Pekerjaan'],
            ['Data Hasil Sinkron', 'Data Sinkronisasi'],
            ['Sinkron Semua ARKAS', 'Ambil Data ARKAS'],
            ['Integrasi ARKAS', 'Pengaturan ARKAS'],
            ['Integrasi Dapodik', 'Pengaturan Dapodik'],
            ['Manajemen User', 'Kelola Pengguna'],
            ['Uji Sebagai User', 'Impersonate'],
            ['Backup & Restore', 'Backup & Restore'],
            ['Database Aktif', 'Database Aktif'],
            ['Pajak Sinkronisasi', 'Data Pajak'],
            ['WORKFLOW SPJ & PENOMORAN', 'ALUR KERJA SPJ & PENOMORAN'],
            ['Preview antrean penomoran', 'Periksa paket yang akan diberi nomor'],
            ['Preview sebelum penomoran', 'Periksa sebelum membuat nomor'],
            ['Terapkan Filter', 'Terapkan'],
            ['Reset Filter', 'Hapus Saringan'],
            ['Reset Periode', 'Hapus Saringan'],
            ['Filter antrean rekonsiliasi', 'Saring transaksi yang perlu diperiksa'],
            ['Simpan Profil Aktif', 'Simpan Perubahan'],
            ['Buat Sekolah dan Database', 'Tambah Sekolah'],
            ['Tambah Tahun Anggaran', 'Tambahkan Tahun Anggaran'],
            ['Buka Paket SPJ', 'Lihat Paket SPJ'],
            ['Ruang Kerja SPJ', 'Pekerjaan SPJ'],
            ['Data SPJ Operator', 'Data SPJ yang Diisi'],
            ['Data ARKAS / BKU', 'Data dari ARKAS / BKU'],
            ['Hanya dibaca', 'Tidak dapat diubah'],
            ['Read only', 'Tidak dapat diubah'],
            ['Read-only', 'Tidak dapat diubah'],
        ]);

        const phrases = [
            [/\bworkflow\b/gi, 'Alur Kerja'],
            [/\bpreview\b/gi, 'Pratinjau'],
            [/\bfilter\b/gi, 'Filter'],
            [/\breset\b/gi, 'Reset'],
            [/\bmapping\b/gi, 'Pemetaan'],
            [/\bbatch\b/gi, 'Proses'],
            [/\buser\b/gi, 'Pengguna'],
            [/\bsync\b/gi, 'Sinkronisasi'],
            [/\bdownload\b/gi, 'Unduh'],
            [/\bupload\b/gi, 'Unggah'],
            [/\bread[ -]?only\b/gi, 'Tidak dapat diubah'],
            [/\bplaceholder\b/gi, 'Penanda data'],
            [/\bdraft\b/gi, 'Belum lengkap'],
        ];

        const simplifyExact = (value) => {
            if (!value || typeof value !== 'string') return value;
            const trimmed = value.trim();
            if (!exact.has(trimmed)) return value;
            return value.replace(trimmed, exact.get(trimmed));
        };

        const simplifyUiTerm = (value) => {
            let result = simplifyExact(value);
            phrases.forEach(([pattern, replacement]) => {
                result = result.replace(pattern, replacement);
            });
            return result;
        };

        const excluded = 'script, style, code, pre, textarea, [contenteditable="true"], [data-preserve-ui-copy]';
        const uiTextContext =
            'button, a, label, option, th, summary, legend, h1, h2, h3, h4, nav, [role="tab"], [role="button"], [role="menuitem"]';

        const simplifyNode = (root) => {
            if (!root) return;
            const scope = root.nodeType === Node.ELEMENT_NODE ? root : root.parentElement;
            if (scope?.matches?.(excluded) || scope?.closest?.(excluded)) return;

            const walkerRoot = root.nodeType === Node.TEXT_NODE ? root.parentElement : root;
            if (!walkerRoot) return;

            const walker = document.createTreeWalker(
                walkerRoot,
                NodeFilter.SHOW_TEXT, {
                    acceptNode(node) {
                        const parent = node.parentElement;
                        if (!parent || parent.matches(excluded) || parent.closest(excluded))
                            return NodeFilter.FILTER_REJECT;
                        return NodeFilter.FILTER_ACCEPT;
                    },
                },
            );

            const nodes = [];
            if (root.nodeType === Node.TEXT_NODE) nodes.push(root);
            while (walker.nextNode()) nodes.push(walker.currentNode);

            nodes.forEach((node) => {
                const parent = node.parentElement;
                const original = node.nodeValue;
                const changed = parent?.closest(uiTextContext) ?
                    simplifyUiTerm(original) :
                    simplifyExact(original);
                if (changed !== original) node.nodeValue = changed;
            });

            const elementRoot = root.nodeType === Node.ELEMENT_NODE ? root : root.parentElement;
            if (!elementRoot) return;
            const elements = [elementRoot, ...elementRoot.querySelectorAll(
                '[title], [aria-label], [placeholder]')];
            elements.forEach((element) => {
                if (element.matches(excluded) || element.closest(excluded)) return;
                ['title', 'aria-label', 'placeholder'].forEach((attribute) => {
                    if (!element.hasAttribute(attribute)) return;
                    const original = element.getAttribute(attribute);
                    const changed = simplifyUiTerm(original);
                    if (changed !== original) element.setAttribute(attribute, changed);
                });
            });
        };

        const run = () => simplifyNode(document.body);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run, {
                once: true
            });
        } else {
            run();
        }

        let scheduled = false;
        const observer = new MutationObserver((mutations) => {
            if (scheduled) return;
            if (!mutations.some((mutation) => mutation.addedNodes.length || mutation.type ===
                    'characterData')) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                run();
            });
        });

        const startObserver = () => observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
        if (document.body) startObserver();
        else document.addEventListener('DOMContentLoaded', startObserver, {
            once: true
        });
    })();
</script>
