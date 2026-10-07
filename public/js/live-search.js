document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-live-search]').forEach((form) => {
        const searchInput = form.querySelector('input[name="search"]');
        let list = document.getElementById(form.dataset.liveSearchList);
        let pagination = document.getElementById(form.dataset.liveSearchPagination);
        let count = document.getElementById(form.dataset.liveSearchCount);
        let notice = document.getElementById(form.dataset.liveSearchNotice);

        if (! searchInput || ! list) {
            return;
        }

        let activeRequest;

        const updateResults = async () => {
            activeRequest?.abort();
            const request = new AbortController();
            activeRequest = request;

            const url = new URL(form.action, window.location.origin);
            const formData = new FormData(form);

            formData.forEach((value, name) => {
                url.searchParams.set(name, value.toString());
            });

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: request.signal,
                });

                if (! response.ok) {
                    return;
                }

                const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextList = documentResponse.getElementById(form.dataset.liveSearchList);
                const nextPagination = documentResponse.getElementById(form.dataset.liveSearchPagination);
                const nextCount = documentResponse.getElementById(form.dataset.liveSearchCount);
                const nextNotice = documentResponse.getElementById(form.dataset.liveSearchNotice);

                if (request.signal.aborted || ! nextList) {
                    return;
                }

                list.replaceWith(nextList);
                list = nextList;

                if (pagination && nextPagination) {
                    pagination.replaceWith(nextPagination);
                    pagination = nextPagination;
                } else if (pagination) {
                    pagination.remove();
                    pagination = null;
                }

                if (count && nextCount) {
                    count.replaceWith(nextCount);
                    count = nextCount;
                }

                if (notice && nextNotice) {
                    notice.replaceWith(nextNotice);
                    notice = nextNotice;
                }

                window.history.replaceState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Carian tidak dapat dikemas kini.', error);
                }
            }
        };

        searchInput.addEventListener('input', updateResults);

        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', updateResults);
        });
    });
});
