<!--begin::Theme mode setup on page load-->
{{-- Admin's persisted theme lives under `apex-admin-bs-theme`, NOT
     Metronic's default `data-bs-theme` localStorage key. Reason: the
     customer portal / shop layouts force `data-bs-theme="light"` on
     <html> via inline script, and when scripts.bundle.js initializes
     on those pages it syncs the attribute back to
     localStorage["data-bs-theme"]. If admin read from that same key,
     visiting the customer portal would silently reset admin's
     dark-mode preference to light.

     We mirror writes both ways so Metronic's existing toggle UI keeps
     working without modification: on init we re-hydrate from our key,
     and on every `kt.thememode.change` event we sync the current
     value back to our key. --}}
<script>
	(function () {
		var defaultThemeMode = "light";
		if (!document.documentElement) return;

		var themeMode;
		if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
			themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
		} else if (localStorage.getItem("apex-admin-bs-theme") !== null) {
			themeMode = localStorage.getItem("apex-admin-bs-theme");
		} else if (localStorage.getItem("data-bs-theme") !== null) {
			// First-load fallback for users who already had a Metronic
			// preference saved before this fix shipped — copy it over
			// so they don't lose their setting on the upgrade.
			themeMode = localStorage.getItem("data-bs-theme");
			localStorage.setItem("apex-admin-bs-theme", themeMode);
		} else {
			themeMode = defaultThemeMode;
		}

		if (themeMode === "system") {
			themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
		}

		document.documentElement.setAttribute("data-bs-theme", themeMode);
		// Re-prime Metronic's own key so its in-page init agrees with us,
		// and stamp our own key so a first-ever toggle has something to
		// fall back to.
		localStorage.setItem("data-bs-theme", themeMode);
		localStorage.setItem("apex-admin-bs-theme", themeMode);

		// Watch the data-bs-theme attribute directly — Metronic's
		// `kt.thememode.change` event flows through its custom
		// KTEventHandler registry, NOT real DOM events, so
		// addEventListener never catches the toggle. A MutationObserver
		// on the attribute is the only reliable hook that fires for
		// every theme change (Metronic toggle, manual setter, anything).
		if (typeof MutationObserver === "function") {
			new MutationObserver(function () {
				var current = document.documentElement.getAttribute("data-bs-theme");
				if (current && current !== "system") {
					localStorage.setItem("apex-admin-bs-theme", current);
				}
			}).observe(document.documentElement, {
				attributes: true,
				attributeFilter: ["data-bs-theme"],
			});
		}
	})();
</script>
<!--end::Theme mode setup on page load-->