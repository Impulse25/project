(function(){
    const STORAGE_KEY = 'svgtk-theme';
    function applyTheme(theme){
        const safeTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', safeTheme);
        localStorage.setItem(STORAGE_KEY, safeTheme);
        document.querySelectorAll('[data-theme-option]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.themeOption === safeTheme);
        });
    }
    window.SVGTKTheme = {
        get(){ return localStorage.getItem(STORAGE_KEY) || 'dark'; },
        set(theme){ applyTheme(theme); },
        toggle(){ applyTheme(this.get() === 'dark' ? 'light' : 'dark'); }
    };
    document.addEventListener('DOMContentLoaded', function(){
        applyTheme(window.SVGTKTheme.get());
        document.querySelectorAll('[data-theme-option]').forEach(btn => {
            btn.addEventListener('click', function(){
                applyTheme(this.dataset.themeOption);
                setTimeout(() => location.reload(), 120);
            });
        });
        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            btn.addEventListener('click', function(){
                window.SVTKTheme = window.SVTKTheme || window.SVGTKTheme;
                window.SVGTKTheme.toggle();
                setTimeout(() => location.reload(), 120);
            });
        });
    });
})();
