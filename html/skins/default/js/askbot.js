/* askbot_php - shared browser helpers.
   Vue 3 is loaded globally; single pages mount their own small app. */

(function () {
  "use strict";

  const askbot = window.askbot || (window.askbot = {});

  /**
   * Call the JSON API. Returns the "result" object or throws with the
   * message the server sent.
   */
  askbot.api = async function (endpoint, params = {}, method = "POST") {
    const url = askbot.baseUrl + "/api/" + endpoint + ".json";
    const options = { method, headers: { "X-CSRF-Token": askbot.csrfToken } };

    if (method === "GET") {
      const query = new URLSearchParams(params).toString();
      return request(query ? url + "?" + query : url, options);
    }
    const body = new FormData();
    body.append("csrf_token", askbot.csrfToken);
    Object.entries(params).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(v => body.append(key + "[]", v));
      else if (value !== null && value !== undefined) body.append(key, value);
    });
    options.body = body;
    return request(url, options);
  };

  async function request(url, options) {
    let response;
    try {
      response = await fetch(url, options);
    } catch (e) {
      throw new Error(askbot.i18n.networkError);
    }
    let data;
    try {
      data = await response.json();
    } catch (e) {
      throw new Error(askbot.i18n.networkError);
    }
    if (data.err && data.err.id !== 0) throw new Error(data.err.msg || askbot.i18n.networkError);
    return data.result;
  }

  /** Small bootstrap toast in the bottom right corner. */
  askbot.toast = function (message, type = "success") {
    const area = document.getElementById("toastArea");
    if (!area) { alert(message); return; }
    const el = document.createElement("div");
    el.className = "toast align-items-center text-bg-" + type + " border-0";
    el.setAttribute("role", "alert");
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    el.querySelector(".toast-body").textContent = message;
    area.appendChild(el);
    const toast = new bootstrap.Toast(el, { delay: type === "danger" ? 6000 : 3000 });
    toast.show();
    el.addEventListener("hidden.bs.toast", () => el.remove());
  };

  askbot.timeAgo = function (isoDate) {
    const seconds = Math.max(0, (Date.now() - Date.parse(isoDate + "Z")) / 1000);
    if (seconds < 60) return Math.floor(seconds) + "s";
    if (seconds < 3600) return Math.floor(seconds / 60) + "m";
    if (seconds < 86400) return Math.floor(seconds / 3600) + "h";
    return Math.floor(seconds / 86400) + "d";
  };

  // --- dark mode ----------------------------------------------------------
  const html = document.documentElement;
  const stored = localStorage.getItem("askbot-theme");
  if (stored) html.setAttribute("data-bs-theme", stored);
  else if (window.matchMedia("(prefers-color-scheme: dark)").matches) html.setAttribute("data-bs-theme", "dark");

  document.addEventListener("click", function (event) {
    const toggle = event.target.closest("#themeToggle");
    if (!toggle) return;
    const next = html.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
    html.setAttribute("data-bs-theme", next);
    localStorage.setItem("askbot-theme", next);
  });

  // --- search suggestions -------------------------------------------------
  const searchForm = document.getElementById("searchBox");
  if (searchForm) {
    const input = searchForm.querySelector("input[name=q]");
    const list = document.createElement("div");
    list.className = "list-group position-absolute top-100 start-0 w-100 shadow search-suggestions d-none";
    searchForm.appendChild(list);

    let timer = null;
    input.addEventListener("input", function () {
      clearTimeout(timer);
      const term = input.value.trim();
      if (term.length < 2) { list.classList.add("d-none"); return; }
      timer = setTimeout(async function () {
        try {
          const result = await askbot.api("search.suggest", { q: term }, "GET");
          list.innerHTML = "";
          (result.questions || []).forEach(function (question) {
            const item = document.createElement("a");
            item.className = "list-group-item list-group-item-action py-2";
            item.href = askbot.baseUrl + "/question/" + question.id + "/" + question.slug;
            item.textContent = question.title;
            list.appendChild(item);
          });
          list.classList.toggle("d-none", list.children.length === 0);
        } catch (e) { list.classList.add("d-none"); }
      }, 220);
    });
    document.addEventListener("click", function (event) {
      if (!searchForm.contains(event.target)) list.classList.add("d-none");
    });
  }
})();
