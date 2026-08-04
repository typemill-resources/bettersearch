var searchForm = document.getElementById("searchForm");
if(searchForm)
{
    var searchIndex         = false;
    var documents           = false;
    var language            = searchForm.dataset.language;
    var token               = searchForm.dataset.token;
    var project             = searchForm.dataset.project;
    var searchplaceholder   = searchForm.dataset.searchplaceholder;
    var noresulttitle       = searchForm.dataset.noresulttitle;
    var noresulttext        = searchForm.dataset.noresulttext;
    var allfiltertext       = searchForm.dataset.allfiltertext;
    var filterCounts        = {};
    try {
        searchFilters = JSON.parse(searchForm.dataset.filter);
    } catch (error) {
        searchFilters = false;
    }

    searchForm.addEventListener('click', function() 
    {
        openSearch();
    });
}

function openSearch() 
{
    // Create modal elements
    var modal = document.createElement('div');
    modal.id = 'searchModal';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-container">
                <div class="modal-filter" id="modalFilter">
                    <!-- Placeholder for filters -->
                </div>
                <div class="modal-search">
                    <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="display:none">
                        <symbol id="icon-search" viewBox="0 0 20 20">
                            <path d="M12.9 14.32c-1.34 1.049-3.050 1.682-4.908 1.682-4.418 0-8-3.582-8-8s3.582-8 8-8c4.418 0 8 3.582 8 8 0 1.858-0.633 3.567-1.695 4.925l0.013-0.018 5.35 5.33-1.42 1.42-5.33-5.34zM8 14c3.314 0 6-2.686 6-6s-2.686-6-6-6v0c-3.314 0-6 2.686-6 6s2.686 6 6 6v0z"></path>
                            </symbol>
                    </svg>
                    <span class="searchicon"><svg class="icon icon-search"><use xlink:href="#icon-search"></use></svg></span>        
                    <input type="text" id="modalSearchField" placeholder="` + searchplaceholder + `">
                    <span class="closeModal">&times;</span>
                    <div id="modalSearchResult"></div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('modalSearchField').focus();
    noResult();
    populateFilters();

    if (!searchIndex) {
        tmaxios.get('/indexrs62hgf3p3?token='+token+'+&project='+project).then(function(response) {
            documents = response.data;

            searchIndex = new FlexSearch.Document({
                tokenize: "full",
                document: {
                    store: true,
                    id: "url",
                    index: ["title", "content"]
                }
            });

            var values = Object.values(documents);
            for (let i = 0; i < values.length; i++)
            {
                // stripout links, we need them in other context so they are not stripped out in php
                if (values[i].content)
                {
                    values[i].content = stripMarkdownLinks(values[i].content);
                }
                searchIndex.add(values[i]);
            }
        })
        .catch(function(error) {
            let message = 'Failed to load search index.';

            // Check if server sent a message
            if (error.response && error.response.data)
            {
                if (typeof error.response.data === 'string')
                {
                    message = error.response.data;
                }
            }

            showError(message);
        });
    }

    document.getElementById('modalSearchField').addEventListener("input", function(event)
    {
        runSearch(event);
    }, false);

    document.querySelector('.closeModal').addEventListener('click', function()
    {
        document.body.removeChild(modal);
    });
}

function populateFilters()
{
    var filterContainer = document.getElementById('modalFilter');
    filterCounts = {}; // Initialize filterCounts here

    // Add "all" filter first
    var allFilter = document.createElement('p');
    allFilter.dataset.path = '';
    allFilter.dataset.name = 'all';
    allFilter.textContent = allfiltertext + ' (0)';
    allFilter.addEventListener('click', function(event)
    {
        filterResults(event.target.dataset.path);
    });
    filterContainer.appendChild(allFilter);

    // Initialize count for "All" filter
    filterCounts['all'] = 0;

    if(searchFilters)
    {
        searchFilters.forEach(function(filter)
        {
            // Initialize filter count
            filterCounts[filter.name] = 0;

            var filterElement = document.createElement('p');
            filterElement.dataset.path = filter.path;
            filterElement.dataset.name = filter.name;
            filterElement.textContent = `${filter.name} (0)`;

            filterElement.addEventListener('click', function(event)
            {
                filterResults(event.target.dataset.path);
            });

            filterContainer.appendChild(filterElement);
        });
    }
}

function restructureResults(grouped) {
    const byId = new Map();
    for (const group of grouped) {
        const field = group.field;
        for (const r of group.result) {
            const id = r.id;
            let entry = byId.get(id);
            if (!entry) {
                entry = {
                    id,
                    doc: r.doc || null
                };
                byId.set(id, entry);
            }
            if (!entry[field]) entry[field] = r.highlight;
        }
    }
    return Array.from(byId.values());
}

function runSearch(event)
{
    event.preventDefault();

    var searchField = document.getElementById('modalSearchField');
    var term = searchField.value.trim();

    if (term.length < 2)
    {
        noResult();                // show no results
        resetFilterCounts();       // reset all filter counts to 0
        renderFilters();          // update filter display
        return;
    }

    document.getElementById('modalSearchResult').innerHTML = '';

    var results = restructureResults(searchIndex.search({
        query: term,
        // enrich: true,
        highlight: {
            template: "<span class=\"search-hl\">$1</span>",
            boundary: 100,
            ellipsis: "...",
            merge: true
        }
    }));

    var resultPages = results.map(function(match)
    {
        var singleResult = {
            filtername: match.doc.filtername,
            filterpath: match.doc.filterpath,
            url: match.doc.url,
            hltitle: match.doc.title,
            snippet: match.doc.content.length > 100 ? match.doc.content.substring(0, 100) + '...' : match.doc.content
        };

        if (typeof match.title === 'string') {
            singleResult.hltitle = match.title;
        }

        // --- HIGHLIGHT FIRST OCCURRENCE IN CONTENT ---
        if (typeof match.doc.content === 'string') {
            let content = match.doc.content;
            let idx = content.toLowerCase().indexOf(term.toLowerCase());
            if (idx !== -1) {
                // take 50 chars before and after
                let start = Math.max(0, idx - 50);
                let end   = Math.min(content.length, idx + term.length + 50);
                let snippet = content.slice(start, end);
                snippet = snippet.slice(0, idx - start) +
                          '<span class="search-hl">' +
                          snippet.slice(idx - start, idx - start + term.length) +
                          '</span>' +
                          snippet.slice(idx - start + term.length);
                if (start > 0) snippet = "..." + snippet;
                if (end < content.length) snippet = snippet + "...";
                singleResult.snippet = snippet;
            } else {
                // fallback
                singleResult.snippet = content.substring(0, 100) + '...';
            }
        }

        return singleResult;
    })
    .filter(function(result) {

        /*  Filter out the null/undefined entries */
        
        return result !== null;
    });

    resetFilterCounts();
    updateFilterCounts(resultPages);
    renderFilters();
    renderResults(resultPages, term);
}

function stripMarkdownLinks(text)
{
    // Replace [label](url) → label
    return text.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
}

function resetFilterCounts()
{
    for (var filter in filterCounts)
    {
        filterCounts[filter] = 0;
    }
}

function updateFilterCounts(results)
{
    results.forEach(function(result)
    {
        filterCounts['all']++;
        if (result.filtername) filterCounts[result.filtername]++;
    });
}

function renderFilters()
{
    document.querySelectorAll('#modalFilter p').forEach(function(filterElement)
    {
        var name = filterElement.dataset.name;
        if (filterCounts[name] !== undefined)
        {
            filterElement.textContent = name === 'all' ? `${allfiltertext} (${filterCounts[name]})` : `${name} (${filterCounts[name]})`;
            filterElement.classList.toggle('has-results', filterCounts[name] > 0);
        }
    });
}

function renderResults(results, term)
{
    if (!results || results.length === 0)
    {
        noResult();
        return;
    }
    var resultsString = "<div class='resultwrapper'><ul class='resultlist'>";
    results.forEach(function(r)
    {
        resultsString += "<a href='" + r.url + "?q=" + term + "'>";
        resultsString += "<li class='resultitem' data-path='" + r.filterpath + "' data-name='" + r.filtername + "'>";
        resultsString += "<h3 class='resultheader'>" + r.hltitle + "</h3>";
        resultsString += "<div class='resultsnippet'>" + r.snippet + "</div>";
        resultsString += "</li></a>";
    });
    resultsString += "</ul></div>";
    document.getElementById('modalSearchResult').innerHTML = resultsString;
}

function filterResults(path)
{
    var resultItems = document.querySelectorAll('.resultitem');
    
    resultItems.forEach(function(item)
    {
        if (item.dataset.path.includes(path))
        {
            item.style.display = 'block';
        } 
        else 
        {
            item.style.display = 'none';
        }
    });
}

function noResult()
{
    var resultsString = "<div class='noresultwrapper'>";
    resultsString += "<h3>" + noresulttitle + "</h3>";
    resultsString += "<p>" + noresulttext + "</p>";
    resultsString += "</div>";

    document.getElementById('modalSearchResult').innerHTML = resultsString;
}

function showError(error)
{
    var resultsString = "<div class='noresultwrapper'>";
    resultsString += "<h3>Error</h3>";
    resultsString += "<p>" + error + "</p>";
    resultsString += "</div>";

    document.getElementById('modalSearchResult').innerHTML = resultsString;    
}