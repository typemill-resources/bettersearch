var searchForm = document.getElementById("searchForm");
if(searchForm)
{
    var searchIndex         = false;
    var documents           = false;
    var language            = searchForm.dataset.language;
    var searchplaceholder   = searchForm.dataset.searchplaceholder;
    var noresulttitle       = searchForm.dataset.noresulttitle;
    var noresulttext        = searchForm.dataset.noresulttext;
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
        tmaxios.get('/indexrs62hgf3p3').then(function(response) {
            documents = response.data;
            searchIndex = lunr(function() {
                if (language && language !== 'en') {
                    this.use(lunr[language]);
                }

                this.ref("id");
                this.field("title", { boost: 10 });
                this.field("content");
                this.metadataWhitelist = ['position'];

                for (var key in documents) {
                    this.add({
                        "id": documents[key].url,
                        "title": documents[key].title,
                        "content": documents[key].content
                    });
                }
            });
        }).catch(function(error) {});
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
    allFilter.textContent = 'all (0)';
    allFilter.addEventListener('click', function(event) {
        filterResults(event.target.dataset.path);
    });
    filterContainer.appendChild(allFilter);

    // Initialize count for "All" filter
    filterCounts['All'] = 0;

    if(searchFilters)
    {
        searchFilters.forEach(function(filter) {
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

function runSearch(event)
{
    event.preventDefault();

    var term = document.getElementById('modalSearchField').value;

    if (term.length < 2)
    {
        return;
    }

    document.getElementById('modalSearchResult').innerHTML = '';

    var results = searchIndex.search(term);

    var resultPages = results.map(function(match)
    {
        var singleResult        = documents[match.ref];
        
        if (typeof singleResult.content === 'string') 
        {
            singleResult.snippet = singleResult.content.substring(0, 100);

            var lunrterm            = Object.keys(match.matchData.metadata)[0];

            if (match.matchData.metadata[lunrterm].content !== undefined)
            {
                var positionStart   = match.matchData.metadata[lunrterm].content.position[0][0];
                var positionLength  = match.matchData.metadata[lunrterm].content.position[0][1];
                var positionEnd     = positionStart + positionLength;

                if (positionStart > 50)
                {
                    var snippet     = singleResult.content.slice(positionStart - 50, positionEnd + 50);
                    positionStart   = 50;
                    positionEnd     = 50 + positionLength;
                } 
                else 
                {
                    var snippet     = singleResult.content.slice(0, positionEnd + 100 - positionStart);
                }

                snippet             = snippet.slice(0, positionStart) + '<span class="lunr-hl">' + snippet.slice(positionStart, positionEnd) + '</span>' + snippet.slice(positionEnd, snippet.length) + '...';

                if (positionStart > 50)
                {
                    snippet         = '...' + snippet;
                }

                singleResult.snippet = snippet;
            }

            singleResult.hltitle    = singleResult.title;

            if (match.matchData.metadata[lunrterm].title !== undefined)
            {
                var positionStart   = match.matchData.metadata[lunrterm].title.position[0][0];
                var positionLength  = match.matchData.metadata[lunrterm].title.position[0][1];
                var positionEnd     = positionStart + positionLength;

                singleResult.hltitle = singleResult.title.slice(0, positionStart) + '<span class="lunr-hl">' + singleResult.title.slice(positionStart, positionEnd) + '</span>' + singleResult.title.slice(positionEnd, singleResult.title.length);
            }

            return singleResult;
        }

        return null;
    })
    .filter(function(result) {

        /*  Filter out the null/undefined entries */
        
        return result !== null;
    });

    /* Reset filter counts */
    for (var filter in filterCounts)
    {
        filterCounts['all'] = 0;
        filterCounts[filter] = 0;
    }

    /* Update filter counts */
    resultPages.forEach(function(result)
    {
        filterCounts['all']++;

        if (result.filtername)
        {
            filterCounts[result.filtername]++;
        }
    });

    document.querySelectorAll('#modalFilter p').forEach(function(filterElement) 
    {
        var name = filterElement.dataset.name;
        if (filterCounts[name] !== undefined)
        {
            filterElement.textContent = `${name} (${filterCounts[name]})`;
            if (filterCounts[name] > 0)
            {
                filterElement.classList.add('has-results');
            } 
            else 
            {
                filterElement.classList.remove('has-results');
            }
        }
    });

    if (resultPages === undefined || resultPages.length == 0)
    {
        noResult();
        return;
    }
    else
    {
        var resultsString = "<div class='resultwrapper'>";
        resultsString += "<ul class='resultlist'>";
        resultPages.forEach(function(r)
        {
            resultsString += "<a href='" + r.url + "?q=" + term + "'>";
            resultsString += "<li class='resultitem' data-path='" + r.filterpath + "' data-name='" + r.filtername + "'>";
            resultsString += "<h3 class='resultheader'>" + r.hltitle + "</h3>";
            resultsString += "<div class='resultsnippet'>" + r.snippet + "</div>";
            resultsString += "</li>";
            resultsString += "</a>";
        });
        resultsString += "</ul></div>";

        document.getElementById('modalSearchResult').innerHTML = resultsString;
    }
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