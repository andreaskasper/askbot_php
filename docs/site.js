/* askbot project site - no dependencies, no build step. */
(function () {
  "use strict";

  /* --- theme: follow the system, allow an override ---------------------- */
  var KEY = "askbot-theme";
  try {
    var saved = localStorage.getItem(KEY);
    if (saved === "light" || saved === "dark") {
      document.documentElement.setAttribute("data-theme", saved);
    }
  } catch (e) { /* private mode: stay on the system preference */ }

  function currentTheme() {
    var set = document.documentElement.getAttribute("data-theme");
    if (set) return set;
    return window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
  }

  document.addEventListener("click", function (ev) {
    var toggle = ev.target.closest && ev.target.closest(".theme-toggle");
    if (!toggle) return;
    var next = currentTheme() === "dark" ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", next);
    try { localStorage.setItem(KEY, next); } catch (e) {}
  });

  /* --- mobile navigation ------------------------------------------------ */
  var navToggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".nav");
  if (navToggle && nav) {
    navToggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      navToggle.setAttribute("aria-expanded", String(open));
    });
    nav.addEventListener("click", function (ev) {
      if (ev.target.tagName === "A") {
        nav.classList.remove("open");
        navToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* --- copy buttons ----------------------------------------------------- */
  document.querySelectorAll(".code").forEach(function (block) {
    var head = block.querySelector(".code-head");
    var pre = block.querySelector("pre");
    if (!head || !pre || head.querySelector(".copy")) return;

    var btn = document.createElement("button");
    btn.className = "copy";
    btn.type = "button";
    btn.textContent = "Copy";
    btn.addEventListener("click", function () {
      var text = pre.innerText;
      var done = function () {
        btn.textContent = "Copied";
        btn.classList.add("done");
        setTimeout(function () {
          btn.textContent = "Copy";
          btn.classList.remove("done");
        }, 1600);
      };
      var fallback = function () {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.setAttribute("readonly", "");
        ta.style.position = "absolute";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand("copy"); done(); } catch (e) {}
        document.body.removeChild(ta);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }
    });
    head.appendChild(btn);
  });

  /* --- tiny syntax highlighter -------------------------------------------
     Deliberately simple: comments and strings are set aside first, then
     keywords and numbers are coloured in what remains. Good enough for the
     PHP, SQL, shell and Apache snippets on this site, and it cannot break
     the page if it gets something wrong.                                   */
  var KEYWORDS = {
    shell: /\b(git|mysql|mysqldump|tar|crontab|php|composer|sudo|apt|a2enmod|systemctl|certbot|chown|chmod|mkdir|cd|cp|EOF)\b/g,
    php: /\b(include|define|isset|new|true|false|exit|if|else|echo|return|function|array)\b/g,
    sql: /\b(CREATE|DATABASE|USER|GRANT|ALL|PRIVILEGES|ON|TO|IDENTIFIED|BY|FLUSH|SELECT|UPDATE|INSERT|DELETE|FROM|WHERE|SET|LIMIT)\b/g,
    apache: /\b(RewriteEngine|RewriteBase|RewriteCond|RewriteRule|VirtualHost|DocumentRoot|ServerName|Directory|AllowOverride|Require|On|All)\b/g
  };

  // Placeholders live in the Unicode private use area and contain no digits
  // or ASCII letters, so the keyword and number passes cannot match inside
  // one and corrupt the restore. Written as escapes to keep this file ASCII.
  var PU_START = 0xE100;
  var PLACEHOLDER = new RegExp("[\\uE100-\\uEFFF]", "g");

  document.querySelectorAll("pre[data-lang]").forEach(function (pre) {
    var lang = pre.getAttribute("data-lang");
    var slots = [];

    function stash(html) {
      slots.push(html);
      return String.fromCharCode(PU_START + slots.length - 1);
    }

    var out = pre.textContent
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");

    // Comments first, so a # inside a string is never mistaken for one.
    out = out.replace(/(^|\n)([^\n]*?)(#[^\n]*)/g, function (m, nl, before, comment) {
      if ((before.match(/&quot;/g) || []).length % 2 === 1) return m;
      return nl + before + stash('<span class="t-com">' + comment + "</span>");
    });

    out = out.replace(/(&quot;(?:(?!&quot;)[\s\S])*&quot;)/g, function (m) {
      return stash('<span class="t-str">' + m + "</span>");
    });

    if (KEYWORDS[lang]) {
      out = out.replace(KEYWORDS[lang], function (m) {
        return '<span class="t-key">' + m + "</span>";
      });
    }
    out = out.replace(/\b(\d[\d_.]*)\b/g, '<span class="t-num">$1</span>');

    out = out.replace(PLACEHOLDER, function (ch) {
      return slots[ch.charCodeAt(0) - PU_START];
    });
    pre.innerHTML = out;
  });

  /* --- table of contents highlighting ----------------------------------- */
  var links = Array.prototype.slice.call(document.querySelectorAll(".toc a"));
  if (links.length && "IntersectionObserver" in window) {
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute("href").slice(1)] = a; });

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        var link = byId[entry.target.id];
        if (!link) return;
        if (entry.isIntersecting) {
          links.forEach(function (a) { a.classList.remove("active"); });
          link.classList.add("active");
        }
      });
    }, { rootMargin: "-88px 0px -70% 0px", threshold: 0 });

    Object.keys(byId).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) observer.observe(el);
    });
  }

  /* --- hero demo: a question types itself, an answer arrives ------------- */
  var typer = document.getElementById("demo-typer");
  if (typer) {
    var answerBox = document.querySelector(".demo-a");
    var answerText = document.getElementById("demo-answer");
    var votesEl = document.getElementById("demo-votes");
    var caret = document.querySelector(".caret");
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var pairs = [
      { q: "How do I install askbot on my own server?",
        a: "Clone the repository into your web root, create a MySQL database and open the site in a browser — the built-in installer walks you through the rest.",
        v: 17 },
      { q: "Can my board speak German and English?",
        a: "Yes. Gettext locales for de_DE and en_US ship with it, and the frontend.pot template makes adding another language a translation job, not a coding job.",
        v: 9 },
      { q: "How do I change how the board looks?",
        a: "Copy src/skins/default, adjust it, and select your new skin — the presentation layer is separate from the code.",
        v: 12 },
      { q: "Do guests need an account to answer?",
        a: "No — anonymous answers are allowed, guarded by a captcha and, if you configure a key, by Akismet.",
        v: 14 }
    ];

    if (reduced) {
      var p0 = pairs[0];
      typer.textContent = p0.q;
      answerText.textContent = p0.a;
      votesEl.textContent = String(p0.v);
      answerBox.classList.add("show", "accepted-show");
      if (caret) caret.style.display = "none";
    } else {
      var idx = 0;

      var typeQ = function (q, done) {
        var n = 0;
        (function tick() {
          n += 1;
          typer.textContent = q.slice(0, n);
          if (n < q.length) setTimeout(tick, 26 + Math.random() * 42);
          else done();
        })();
      };

      var countVotes = function (v, done) {
        var c = 0;
        (function tick() {
          votesEl.textContent = String(c);
          if (c < v) { c += 1; setTimeout(tick, 60); }
          else done();
        })();
      };

      var run = function () {
        var p = pairs[idx % pairs.length];
        idx += 1;
        typer.textContent = "";
        votesEl.textContent = "0";
        answerText.textContent = p.a;
        answerBox.classList.remove("show", "accepted-show");
        setTimeout(function () {
          typeQ(p.q, function () {
            setTimeout(function () {
              answerBox.classList.add("show");
              countVotes(p.v, function () {
                answerBox.classList.add("accepted-show");
                setTimeout(run, 4600);
              });
            }, 550);
          });
        }, 400);
      };

      run();
    }
  }

  /* --- year -------------------------------------------------------------- */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });
})();
