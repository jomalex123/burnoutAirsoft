(function(window) {

    'use strict';

    $.exists = function(selector) {
        return ($(selector).length > 0);
    }

    window.onpageshow = function(event) {
      if (event.persisted) {
          PageTransition();
            $('.hamburger').removeClass('is-active');
            $('.ms-nav').removeClass('is-visible');
            $('.ms-header').not('.navbar-white').each(function() {
                $('.logo-light').removeClass('active');
            });
      }
    };

    // All Funtions
    PageTransition();
    Menu();
    InitPage();

})(window);

function InitPage() {
    ms_home_slider();
    Sort();
    UniteGallery();
    ValidForm();

    if (typeof window.initAdminPage === 'function') {
        window.initAdminPage();
    }

    if (typeof window.initAdminGalleryPage === 'function') {
        window.initAdminGalleryPage();
    }

    if (typeof window.initAdminRegistrosPage === 'function') {
        window.initAdminRegistrosPage();
    }

    if (typeof window.initAdminPartidasPage === 'function') {
        window.initAdminPartidasPage();
    }

    if (typeof window.initGalleryPage === 'function') {
        window.initGalleryPage();
    }

    if (typeof window.initPartidasPage === 'function') {
        window.initPartidasPage();
    }

    if (typeof window.initRegistroPage === 'function') {
        window.initRegistroPage();
    }

    if (typeof window.initTemplatePage === 'function') {
        window.initTemplatePage();
    }
}

/*--------------------
    Page Transition
---------------------*/
function PageTransition() {
    var isNavigating = false;

    var preload = anime({
        targets: '.ms-preloader',
        opacity: [1, 0],
        duration: 1000,
        easing: 'easeInOutCubic',
        complete: function(preload) {
            $('.ms-preloader').css('visibility', 'hidden');
        }
    });
    $('.ms-main-container').addClass('loaded');
    var cont = anime({
        targets: '.loaded',
        opacity: [0, 1],
        easing: 'easeInOutCubic',
        duration: 1000,
        delay: 300,
        complete: function(preload) {
            $('.ug-thumb-image').css({
                'opacity': '1'
            });
            $('.ms-section__block img').css({
                'opacity': '1'
            });
            $('.ug-thumb-wrapper, .post-item').css({
                'pointer-events': 'auto'
            });
        }
    });
    $(document).off('click.pageTransition').on('click.pageTransition', '[data-type="page-transition"]', function(e) {
        var target = getPageTransitionTarget(this, e);

        if (!target) {
            return;
        }

        e.preventDefault();

        if (!target.partial) {
            navigateWithFullReload(target.url.href);
            return;
        }

        navigateToPage(target.url.href, true);
    });

    window.onpopstate = function() {
        navigateToPage(window.location.href, false);
    };

    window.BurnoutNavigate = function(url) {
        navigateToPage(new URL(url, window.location.href).href, true);
    };

    function navigateToPage(url, pushState) {
        if (isNavigating) {
            return;
        }

        isNavigating = true;
        closeNavigation();

        anime({
            targets: '.ms-container',
            opacity: [1, 0],
            translateY: [0, 12],
            duration: 220,
            easing: 'easeInOutQuad',
            complete: function() {
                fetch(url)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('No se pudo cargar la pagina.');
                        }

                        return response.text();
                    })
                    .then(function(html) {
                        var parser = new DOMParser();
                        var page = parser.parseFromString(html, 'text/html');
                        var nextMain = page.querySelector('main.ms-container');
                        var detachedPageElements = [];

                        if (!nextMain) {
                            window.location.href = url;
                            return;
                        }

                        detachedPageElements = collectDetachedPageElements(page, nextMain);

                        loadPageStyles(page).then(function() {
                            document.title = page.title || document.title;
                            document.querySelector('main.ms-container').replaceWith(nextMain);
                            syncDetachedPageElements(detachedPageElements);

                            loadPageScripts(page, function() {
                                InitPage();
                                window.scrollTo(0, 0);

                                if (pushState) {
                                    history.pushState({}, document.title, url);
                                }

                                anime({
                                    targets: '.ms-container',
                                    opacity: [0, 1],
                                    translateY: [12, 0],
                                    duration: 260,
                                    easing: 'easeInOutQuad',
                                    complete: function() {
                                        isNavigating = false;
                                    }
                                });
                            });
                        });
                    })
                    .catch(function() {
                        window.location.href = url;
                    });
            }
        });
    }

    function navigateWithFullReload(url) {
        $('.ms-preloader').css('visibility', 'visible');

        anime({
            targets: '.ms-preloader',
            opacity: [0, 1],
            duration: 300,
            easing: 'easeInOutQuad',
            complete: function() {
                window.location.href = url;
            }
        });
    }
}

function getPageTransitionTarget(link, event) {
    var href = link.getAttribute('href');

    if (!href || href === '#' || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return null;
    }

    var url = new URL(href, window.location.href);

    if (url.origin !== window.location.origin) {
        return null;
    }

    if (url.hash && url.pathname === window.location.pathname) {
        return null;
    }

    return {
        url: url,
        partial: !isIndexPath(url.pathname) && !isIndexPath(window.location.pathname)
    };
}

function isIndexPath(pathname) {
    var path = pathname.replace(/\/+$/, '');

    return path === '' || path === '/burnoutAirsoft' || /\/index\.html$/i.test(path);
}

function closeNavigation() {
    $('.hamburger').removeClass('is-active');
    $('.ms-nav').removeClass('is-visible');
    $('.logo-light').removeClass('active');
}

function loadPageStyles(page) {
    var baseStyles = [
        'assets/css/plugins.min.css',
        'assets/css/style.css'
    ];
    var styles = [];

    $(page).find('link[rel="stylesheet"]').each(function() {
        var href = this.getAttribute('href');

        if (!href || baseStyles.indexOf(href) !== -1 || $('link[href="' + href + '"]').length) {
            return;
        }

        styles.push(new Promise(function(resolve) {
            var link = $('<link>', {
                rel: 'stylesheet',
                href: href,
                'data-page-style': 'true'
            }).on('load error', resolve);

            setTimeout(resolve, 1500);
            link.appendTo('head');
        }));
    });

    return Promise.all(styles);
}

function collectDetachedPageElements(page, nextMain) {
    var selectors = [
        '#modal',
        '#normativaModal',
        '#registroConfirmationModal'
    ];

    return selectors.reduce(function(elements, selector) {
        var nextElement = page.querySelector(selector);

        if (nextElement && !nextMain.contains(nextElement)) {
            elements.push(document.importNode(nextElement, true));
        }

        return elements;
    }, []);
}

function syncDetachedPageElements(detachedPageElements) {
    var selectors = [
        '#modal',
        '#normativaModal',
        '#registroConfirmationModal'
    ];
    var container = document.querySelector('.ms-main-container') || document.body;
    var currentMain = document.querySelector('main.ms-container');

    $('body').removeClass('registro-modal-open');

    selectors.forEach(function(selector) {
        document.querySelectorAll(selector).forEach(function(element) {
            if (currentMain && currentMain.contains(element)) {
                return;
            }

            element.remove();
        });
    });

    selectors.forEach(function(selector) {
        if (!currentMain) {
            return;
        }

        currentMain.querySelectorAll(selector).forEach(function(element) {
            container.appendChild(element);
        });
    });

    detachedPageElements.forEach(function(element) {
        container.appendChild(element);
    });
}

function loadPageScripts(page, done) {
    var baseScripts = [
        'assets/js/jquery-3.2.1.min.js',
        'assets/js/plugins.min.js',
        'assets/js/main.js'
    ];
    var scripts = [];

    $(page).find('script[src]').each(function() {
        var src = this.getAttribute('src').replace(/^['"]|['"]$/g, '');

        if (baseScripts.indexOf(src) === -1) {
            scripts.push(src);
        }
    });

    loadScriptQueue(scripts, done);
}

function loadScriptQueue(scripts, done) {
    var src = scripts.shift();

    if (!src) {
        done();
        return;
    }

    if ($('script[src="' + src + '"]').length) {
        loadScriptQueue(scripts, done);
        return;
    }

    var script = document.createElement('script');
    script.src = src;
    window.__burnoutLoadingPageScript = true;
    script.onload = function() {
        window.__burnoutLoadingPageScript = false;
        loadScriptQueue(scripts, done);
    };
    script.onerror = function() {
        window.__burnoutLoadingPageScript = false;
        loadScriptQueue(scripts, done);
    };

    document.body.appendChild(script);
}

/*------------------
    Menu
-------------------*/
function Menu() {
    if ($.exists('.hamburger')) {
        $('.hamburger').on('click', function(e) {
            var burger = $(this);
            $(burger).toggleClass('is-active');
            $('.ms-nav').toggleClass('is-visible');
            $('.ms-header').not('.navbar-white').each(function() {
                $('.logo-light').toggleClass('active');
            });
        });
        $('.height-full-viewport').on({'mousewheel': function(e) {
            if (e.target.id === 'el') return;
            e.preventDefault();
            e.stopPropagation();
        }
})
    }
}

/*------------------
    Home Slider
-------------------*/
    function ms_home_slider() {
        if ($.exists('.swiper-container')) {
            var swiper = new Swiper('.swiper-container', {
            loop: false,
            speed: 1000,
            grabCursor: false,
            mousewheel: true,
            keyboard: true,
            simulateTouch: false,
            parallax: true,
            effect: 'slide',
            pagination: {
                el: '.swiper-pagination',
                type: 'progressbar',
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            }
            });
            $('.expanded-timeline__counter span:first-child').text('1');
            $('.expanded-timeline__counter span:last-child').text(swiper.slides.length);
            swiper.on('slideChange', function () {
                $('.expanded-timeline__counter span:first-child').text(swiper.activeIndex + 1);
            });

            }
    }

/*------------------
 Sort
-------------------*/
function Sort() {
    if ($.exists('.filtr-container')) {
        $('.filtr-container').filterizr();
        $('.filtr-btn li').on('click', function() {
            $('.filtr-btn li').removeClass('active');
            $(this).addClass('active');
        });
    }
}
/*------------------
 Unite-Gallery
-------------------*/
function UniteGallery() {
    if ($.exists('#gallery')) {
        $('#gallery').unitegallery({
            gallery_theme: 'tiles',
            tiles_type: "justified",
            tiles_col_width: 400,
            tiles_justified_row_height: 400,
            tiles_justified_space_between: 30,
            // tile_overlay_color: "#000",
            tile_overlay_opacity: 0.7,
            tile_enable_icons: false,
            tile_textpanel_position: "inside_bottom",
        });
    }
}
/*------------------
 Form Validation
-------------------*/
function ValidForm() {
    if ($.exists('#validForm')) {
        $('.form-control').focus(function() {
            $(this).prev('.control-label').addClass('active');
        });
        $('.form-control').focusout(function() {
            $(this).prev('.control-label').removeClass('active');
        });
        $("#validForm").validate({
            ignore: ":hidden",
            rules: {
                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 16,
                },
                email: {
                    required: true,
                    email: true,
                },
                subject: {
                    required: true,
                    minlength: 4,
                    maxlength: 32,
                },
                message: {
                    required: true,
                    minlength: 16,
                },
            },
            messages: {
                name: {
                    required: "<span>Please enter your name</span>",
                    minlength: "<span>Your name must consist of at least 2 characters</span>",
                    maxlength: "<span>The maximum number of characters - 24</span>",
                },
                email: {
                    required: "<span>Please enter your email</span>",
                    email: "<span>Please enter a valid email address.</span>",
                },
                subject: {
                    required: "<span>Please enter your subject</span>",
                    minlength: "<span>Your name must consist of at least 2 characters</span>",
                    maxlength: "<span>The maximum number of characters - 16</span>",
                },
                message: {
                    required: "<span>Please write me message</span>",
                    minlength: "<span>Your message must consist of at least 16 characters</span>",
                    maxlength: "<span>The maximum number of characters - 100 </span>",
                },
            },
            submitHandler: function(form) {
                $.ajax({
                    type: "POST",
                    url: "contact.php",
                    data: $(form).serialize(),
                    beforeSend: function() {
                        // do something
                    },
                    success: function(data) {
                        if (data == "Email sent!");
                        $('input, textarea').val('');
                        $('.form-group').blur();
                        // do something
                    }
                });
                return false;
            }
        });
    }
}
