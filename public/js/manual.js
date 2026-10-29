document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('moduleSearch');
    const cards = [...document.querySelectorAll('#moduleGrid .module-card')];
    const count = document.getElementById('moduleCount');
    const empty = document.getElementById('emptyModules');
    if (!search || !count || !empty) return;

    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        for (const card of cards) {
            const match = card.textContent.toLocaleLowerCase().includes(query);
            card.hidden = !match;
            if (match) visible++;
        }
        count.textContent = query ? `Showing ${visible} of ${cards.length} modules` : `Showing all ${cards.length} modules`;
        empty.hidden = visible !== 0;
    });
});
