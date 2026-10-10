const normalize = (value) => String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, " ")
    .toLocaleLowerCase()
    .trim();

export function initializeRecipientSearch(root = document) {
    root.querySelectorAll("[data-recipient-search-scope]").forEach((scope) => {
        const input = scope.querySelector("[data-recipient-search]");
        if (!input) return;

        const clear = scope.querySelector("[data-recipient-search-clear]");
        const selectedCount = scope.querySelector("[data-recipient-selected-count]");
        const checkboxes = [...scope.querySelectorAll('input[name="recipient_ids[]"]')];

        const updateSelection = () => {
            if (selectedCount) {
                selectedCount.textContent = `${checkboxes.filter((checkbox) => checkbox.checked && !checkbox.disabled).length} selected`;
            }
        };

        const filter = () => {
            const query = normalize(input.value);
            const phoneQuery = /^[+\d\s()-]+$/.test(query) ? query.replace(/\D/g, "") : "";
            let total = 0;

            scope.querySelectorAll("[data-recipient-group]").forEach((group) => {
                let visible = 0;
                group.querySelectorAll("[data-recipient-row]").forEach((row) => {
                    const text = normalize(row.dataset.recipientSearchText);
                    const phone = (row.dataset.recipientPhone ?? "").replace(/\D/g, "");
                    const localPhone = phone.replace(/^639/, "09");
                    const matches = !query || text.includes(query) ||
                        (phoneQuery && (phone.includes(phoneQuery) || localPhone.includes(phoneQuery)));
                    row.hidden = !matches;
                    if (matches) visible++;
                });
                const count = group.querySelector("[data-recipient-group-count]");
                if (count) count.textContent = String(visible);
                const empty = group.querySelector("[data-recipient-empty]");
                if (empty) empty.hidden = visible > 0;
                total += visible;
            });

            const count = scope.querySelector("[data-recipient-result-count]");
            if (count) count.textContent = `${total} shown`;
            if (clear) clear.disabled = !input.value;
        };

        input.addEventListener("input", filter);
        clear?.addEventListener("click", () => {
            input.value = "";
            filter();
            input.focus();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener("change", updateSelection));
        filter();
        updateSelection();
    });
}
