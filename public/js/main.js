// WMS Pro — Main JavaScript
// Handles: Sidebar toggle (mobile + desktop), Theme toggle, Responsive behavior

(function () {
    'use strict';

    // ===== DOM Elements =====
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appWrapper = document.querySelector('.app-wrapper');

    // ===== Theme Toggle =====
    const themeToggleBtn = document.querySelector('.theme-toggle-btn');
    const themeIconDark = document.querySelector('.theme-icon-dark');
    const themeIconLight = document.querySelector('.theme-icon-light');

    function getTheme() {
        return localStorage.getItem('wms_theme') || 'dark';
    }

    function setTheme(theme) {
        localStorage.setItem('wms_theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        updateThemeIcons(theme);
    }

    function updateThemeIcons(theme) {
        if (themeIconDark && themeIconLight) {
            if (theme === 'dark') {
                themeIconDark.style.display = 'block';
                themeIconLight.style.display = 'none';
            } else {
                themeIconDark.style.display = 'none';
                themeIconLight.style.display = 'block';
            }
        }
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const current = getTheme();
            const next = current === 'dark' ? 'light' : 'dark';
            setTheme(next);
        });
    }

    // Initialize theme icons on load
    updateThemeIcons(getTheme());

    // ===== Sidebar Toggle Logic =====
    const MOBILE_BREAKPOINT = 768;
    const TABLET_MAX = 1024;

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    function isTablet() {
        return window.innerWidth > MOBILE_BREAKPOINT && window.innerWidth <= 1024;
    }

    function isDesktop() {
        return window.innerWidth > 1024;
    }

    // Mobile: open/close sidebar with overlay
    function openMobileSidebar() {
        if (!sidebar || !sidebarOverlay) return;
        sidebar.classList.add('open');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // prevent background scroll
        sidebarOverlay.setAttribute('aria-hidden', 'false');
    }

    function closeMobileSidebar() {
        if (!sidebar || !sidebarOverlay) return;
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
        sidebarOverlay.setAttribute('aria-hidden', 'true');
    }

    // Desktop/Tablet: collapse/expand sidebar
    function toggleDesktopSidebar() {
        if (!appWrapper) return;
        const isCollapsed = appWrapper.classList.toggle('sidebar-collapsed');
        localStorage.setItem('wms_sidebar_collapsed', isCollapsed ? '1' : '0');
    }

    function setSidebarState() {
        if (!appWrapper) return;
        const saved = localStorage.getItem('wms_sidebar_collapsed');
        if (saved === '1' && (isTablet() || isDesktop())) {
            appWrapper.classList.add('sidebar-collapsed');
        } else if (saved === '0') {
            appWrapper.classList.remove('sidebar-collapsed');
        }
    }

    // Unified toggle handler
    function handleSidebarToggle() {
        if (isMobile()) {
            const isOpen = sidebar.classList.contains('open');
            if (isOpen) closeMobileSidebar();
            else openMobileSidebar();
        } else {
            toggleDesktopSidebar();
        }
    }

    // Close mobile sidebar on overlay click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
    }

    // Sidebar toggle button
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            handleSidebarToggle();
        });
    }

    // Close mobile sidebar on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isMobile() && sidebar?.classList.contains('open')) {
            closeMobileSidebar();
        }
    });

    // Close mobile sidebar when clicking a nav link (on mobile)
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            const link = e.target.closest('.nav-link');
            if (link && isMobile()) {
                closeMobileSidebar();
            }
        });
    }

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (isMobile()) {
                // Mobile: remove desktop collapsed state
                appWrapper?.classList.remove('sidebar-collapsed');
                // Ensure sidebar is closed on resize to mobile
                closeMobileSidebar();
            } else {
                // Desktop/Tablet: restore sidebar state from localStorage
                setSidebarState();
                // Remove mobile open state
                sidebar?.classList.remove('open');
                sidebarOverlay?.classList.remove('active');
                document.body.style.overflow = '';
            }
        }, 150);
    });

    // Initialize sidebar state on load
    setSidebarState();

    // ===== Flash Message Auto-dismiss =====
    const flashMessages = document.querySelectorAll('#flash-message');
    flashMessages.forEach(function (msg) {
        setTimeout(function () {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(function () { msg.remove(); }, 300);
        }, 4000);
    });

    // ===== Form Enhancements =====
    // Auto-focus first invalid field on form submit
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const firstInvalid = form.querySelector('.is-invalid, [aria-invalid="true"]');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        });
    });

    // ===== Table Row Click (for cards with data-href) =====
    document.querySelectorAll('[data-href]').forEach(function (el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function (e) {
            if (e.target.closest('a, button, .action-btns, .form-check')) return;
            window.location.href = this.dataset.href;
        });
    });

    // ===== Print Styles Helper =====
    window.printLabel = function () {
        window.print();
    };

    // ===== Global WMS Loading Overlay & Animation =====
    const loadingOverlay = document.getElementById('wms-loading-overlay');
    const lottieContainer = document.getElementById('wms-lottie-container');
    let lottieAnimation = null;

    function renderCrispSpinner() {
        if (!lottieContainer) return;
        lottieContainer.innerHTML = `
            <div class="wms-loader-graphic">
                <div class="wms-spinner-ring"></div>
                <div class="wms-spinner-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
            </div>
        `;
    }

    function initLottieAnimation() {
        renderCrispSpinner();
    }

    window.showWmsLoading = function (msg) {
        if (!loadingOverlay) return;
        if (msg) {
            const textEl = loadingOverlay.querySelector('.wms-loading-text');
            if (textEl) textEl.textContent = msg;
        }
        initLottieAnimation();
        loadingOverlay.classList.add('active');
    };

    window.hideWmsLoading = function () {
        if (!loadingOverlay) return;
        loadingOverlay.classList.remove('active');
        if (lottieAnimation) {
            lottieAnimation.pause();
        }
    };

    // Auto-trigger loading ONLY on form submissions (saving, updating, deleting)
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.target === '_blank' || form.dataset.noLoading !== undefined) return;
        window.showWmsLoading();
    });

    // Explicit trigger for elements marked with data-loading="true"
    document.addEventListener('click', function (e) {
        const target = e.target.closest('[data-loading="true"]');
        if (target) {
            window.showWmsLoading();
        }
    });

    // Hide loading overlay when page restores from cache or finishes load
    window.addEventListener('pageshow', function () {
        window.hideWmsLoading();
    });

    window.addEventListener('load', function () {
        window.hideWmsLoading();
    });

})();