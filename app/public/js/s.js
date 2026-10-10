/*
 * The site's script, in plain JavaScript since #43: jQuery 1.4.4 (2010), with its published
 * XSS holes, carried 78 KB to every page for a dozen small things, each below with the page it
 * serves. Loaded with defer, so it runs once the page is parsed.
 */
(function () {
    'use strict';

    var YEAR = 350 * 24 * 60 * 60;

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value) {
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + YEAR + '; SameSite=Lax';
    }

    // A POST as jQuery sent it, so the controllers that answer an XMLHttpRequest with JSON still
    // know it is one. It resolves to the JSON, with `error` set when the server did not say yes.
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok && !data.error) {
                    data.error = 'Problem z odpowiedzią serwera, spróbuj za jakiś czas.';
                }
                return data;
            });
        });
    }

    // An event for Google Analytics (#161), when its tag is on the page; nothing otherwise
    function track(name, params) {
        if (typeof window.gtag === 'function') {
            window.gtag('event', name, params || {});
        }
    }

    function fadeIn(element) {
        element.classList.remove('fade-in');
        void element.offsetWidth;
        element.classList.add('fade-in');
    }

    // Album page: the tracklist's guests, music and scratches, hidden or shown, remembered
    function tracklistDetails() {
        var link = $('#tracklist span.toggle > a');
        if (!link) {
            return;
        }
        function show(on) {
            link.textContent = on ? 'Ukryj szczegóły' : 'Pokaż szczegóły';
            $$('ul.feat').forEach(function (list) {
                list.classList.toggle('is-hidden', !on);
            });
        }
        var shown = getCookie('albumShowDetails') !== '0';
        show(shown);
        link.addEventListener('click', function (event) {
            event.preventDefault();
            shown = !shown;
            show(shown);
            setCookie('albumShowDetails', shown ? 1 : 0);
        });
    }

    // Album, artist, song and label pages: the generated description beside the written one
    function autoDescription() {
        var link = $('#description span.toggle > a');
        if (!link) {
            return;
        }
        function show(on) {
            link.textContent = on ? 'Ukryj opis standardowy' : 'Pokaż opis standardowy';
            $$('p.auto').forEach(function (paragraph) {
                paragraph.classList.toggle('js-hidden', !on);
            });
        }
        var shown = getCookie('albumShowAuto') === '1';
        show(shown);
        link.addEventListener('click', function (event) {
            event.preventDefault();
            shown = !shown;
            show(shown);
            setCookie('albumShowAuto', shown ? 1 : 0);
        });
    }

    // Every page: the phone's menu (#149), opened and closed by its button and by Escape
    function menu() {
        var button = $('#menu-toggle');
        var header = $('#header');
        if (!button || !header) {
            return;
        }
        function set(open) {
            header.classList.toggle('nav-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        button.addEventListener('click', function () {
            set(!header.classList.contains('nav-open'));
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && header.classList.contains('nav-open')) {
                set(false);
                button.focus();
            }
        });
    }

    // Album, artist, song, label and news pages: the comment form, its character count, its
    // question for anonymous users, its submission without leaving the page, and the new
    // comment at the top of the list
    function comments() {
        var form = $('#post-comment');
        if (!form) {
            return;
        }
        var textarea = $('textarea', form);
        var count = $('#comment-character-count');
        var again = $('#comment-form-show');
        var limit = 1000;

        // The server's question (#41), asked for when someone starts a comment and again after
        // every attempt, as an answer is taken once, right or wrong
        var question = $('#captcha-question');
        var token = $('#captcha-token');
        var answer = $('#captcha-answer');
        var asking = null;
        function ask() {
            if (!question || !token) {
                return;
            }
            if (asking) {
                return;
            }
            token.value = '';
            question.textContent = 'Chwila, przygotowuję pytanie…';
            asking = post('/comments/captcha', new URLSearchParams()).then(function (data) {
                if (data.error || !data.token) {
                    question.textContent = 'Nie udało się przygotować pytania, spróbuj za chwilę.';
                    return;
                }
                token.value = data.token;
                question.textContent = data.question + ' (antyspam)';
                if (answer) {
                    answer.value = '';
                }
            }).catch(function () {
                question.textContent = 'Nie udało się przygotować pytania, spróbuj za chwilę.';
            }).then(function () {
                asking = null;
            });
        }
        if (token) {
            form.addEventListener('focusin', function () {
                if (!token.value) {
                    ask();
                }
            });
        }

        if (textarea && count) {
            textarea.addEventListener('input', function () {
                var left = limit - textarea.value.length;
                if (left < 0) {
                    count.textContent = 'Komentarz nie może mieć więcej niż ' + limit + ' znaków.';
                } else {
                    count.textContent = 'Pozostało ' + left + ' znaków!';
                }
                count.classList.toggle('hidden', left >= 100);
            });
        }

        if (again) {
            again.addEventListener('click', function (event) {
                event.preventDefault();
                form.classList.remove('is-hidden');
                again.classList.add('hidden');
                if (textarea) {
                    textarea.focus();
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = $('input[type="submit"]', form);
            if (button) {
                button.disabled = true;
            }
            post(form.getAttribute('action'), new URLSearchParams(new FormData(form))).then(function (data) {
                if (data.error) {
                    alert(data.error);
                    ask();
                    return;
                }
                if (token) {
                    token.value = '';
                }
                track('post_comment');
                added(data);
            }).catch(function () {
                alert('Problem z dodaniem komentarza, spróbuj za jakiś czas.');
                ask();
            }).then(function () {
                if (button) {
                    button.disabled = false;
                }
            });
        });

        // The server sends the content and the author escaped, as the list shows them
        function added(data) {
            form.classList.add('is-hidden');
            if (textarea) {
                textarea.value = '';
            }
            if (again) {
                again.classList.remove('hidden');
            }
            var now = new Date();
            function two(n) {
                return (n < 10 ? '0' : '') + n;
            }
            var when = now.getFullYear() + '-' + two(now.getMonth() + 1) + '-' + two(now.getDate()) + ' ' +
                two(now.getHours()) + ':' + two(now.getMinutes()) + ':' + two(now.getSeconds());
            var author = data.authorId === null
                ? data.author
                : '<strong><a href="/' + encodeURIComponent(data.author) + '-u' + parseInt(data.authorId, 10) + '.html">' + data.author + '</a></strong>';
            var item = document.createElement('li');
            item.innerHTML = '<span class="br">' + data.content + '</span><span class="secondary">' + author + ' (' + when + ')</span>';
            var list = $('#comments ul');
            if (list) {
                list.insertBefore(item, list.firstChild);
                fadeIn(item);
            }
        }
    }

    // Song page: a logged-in user edits the lyrics in place
    function lyrics() {
        var link = $('#edit-lyrics');
        var paragraph = $('#lyrics p');
        if (!link || !paragraph) {
            return;
        }
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var form = document.createElement('form');
            form.method = 'post';
            form.action = link.getAttribute('href');
            var textarea = document.createElement('textarea');
            textarea.name = 'lyrics';
            textarea.rows = 30;
            textarea.value = paragraph.textContent.trim();
            var save = document.createElement('input');
            save.type = 'submit';
            save.className = 'submit';
            save.value = 'Zapisz';
            form.appendChild(textarea);
            form.appendChild(save);
            var box = document.createElement('div');
            box.className = 'adm';
            box.id = 'adm-lyrics';
            box.appendChild(form);
            paragraph.replaceChildren(box);

            form.addEventListener('submit', function (submitEvent) {
                submitEvent.preventDefault();
                save.disabled = true;
                post(form.action, new URLSearchParams(new FormData(form))).then(function (data) {
                    if (data.success) {
                        paragraph.innerHTML = data.lyrics;
                        fadeIn(paragraph);
                        track('edit_lyrics');
                    } else {
                        alert(data['result-message'] || data.error || 'Problem z zapisaniem formularza, spróbuj za jakiś czas.');
                        save.disabled = false;
                    }
                }).catch(function () {
                    alert('Problem z zapisaniem formularza, spróbuj za jakiś czas.');
                    save.disabled = false;
                });
            });
        });
    }

    // Song page: "this is not the video of this song", the song by its id and the page's token,
    // counted once per visit (#178)
    function flagVideo() {
        var link = $('#rateDown');
        var count = $('#downCount');
        if (!link) {
            return;
        }
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (link.getAttribute('aria-disabled') === 'true') {
                return;
            }
            var body = new URLSearchParams({ song: link.dataset.song || '', token: link.dataset.token || '' });
            post('/api/songs/flag-video', body).then(function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (count && typeof data.count === 'number') {
                    count.textContent = data.count;
                }
                link.textContent = 'Zgłoszone, dzięki!';
                link.setAttribute('aria-disabled', 'true');
                if (data.counted) {
                    track('flag_video');
                }
            }).catch(function () {
                alert('Problem ze zgłoszeniem, spróbuj za jakiś czas.');
            });
        });
    }

    // Song page: the video played and watched to its end (#161). YouTube's player, with its JS
    // API on, says how it is doing once it is asked to: postMessage, no script of YouTube's here.
    function video() {
        var player = $('#clip iframe');
        if (!player) {
            return;
        }
        var origin = new URL(player.src).origin;
        var played = false;
        function listen() {
            player.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 'clip', channel: 'widget' }), origin);
        }
        player.addEventListener('load', listen);
        listen();
        window.addEventListener('message', function (event) {
            if (event.origin !== origin || typeof event.data !== 'string') {
                return;
            }
            var data;
            try {
                data = JSON.parse(event.data);
            } catch (e) {
                return;
            }
            var state = data.event === 'onStateChange' ? data.info
                : (data.event === 'infoDelivery' && data.info ? data.info.playerState : undefined);
            if (state === 1 && !played) {
                played = true;
                track('play_video', { video_title: player.title });
            } else if (state === 0) {
                track('watch_video', { video_title: player.title });
            }
        });
    }

    // Every page: "Ustawienia prywatności" opens Google's consent message again (#162), where
    // AdSense's script brought it; without it the link goes to the privacy page
    function privacySettings() {
        $$('#privacy-settings, #privacy-settings-inline').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (window.googlefc && typeof window.googlefc.showRevocationMessage === 'function') {
                    event.preventDefault();
                    window.googlefc.callbackQueue = window.googlefc.callbackQueue || [];
                    window.googlefc.callbackQueue.push(window.googlefc.showRevocationMessage);
                }
            });
        });
    }

    // Every page with ads on: each unit asks AdSense for its ad (#172), the line AdSense's own code
    // has inline after it; adsbygoogle.js, loaded async in <head>, takes the queue when it comes.
    // Only the units nobody has filled: when AdSense ran first, its own page-level formats are
    // already in the page as filled units, and one request too many is a TagError (#179).
    function ads() {
        $$('ins.adsbygoogle:not([data-adsbygoogle-status])').forEach(function () {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        });
    }

    tracklistDetails();
    autoDescription();
    menu();
    comments();
    lyrics();
    flagVideo();
    video();
    privacySettings();
    ads();
}());
