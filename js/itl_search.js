// load-more.js



jQuery(document).ready(function ($) {

    var page = 1;

    var loading = false;
    var numberOfResult;
    var $loadMoreButton = $('#load-more-button');
    numberOfResult = $('#itlSearchResult').children().length;

    var $postContainer = $('#itlSearchResult');

    var $loader = $('.lds-ellipsis.sr-loader');

    $('#currentCount').html(numberOfResult - 1)
    $loadMoreButton.on('click', function () {

        // Example usage

        var sqValue = getQueryParameter('sq');

        max_page = $loadMoreButton.attr('data-max_page');

        if (!loading && max_page > page) {
            loading = true;
            $loader.addClass('active')
            $loadMoreButton.hide();
            page++;
            $loadMoreButton.attr('data-page', page)
            $.ajax({
                url: itlObj.ajaxurl,
                type: 'post',
                data: {
                    action: 'load_more_posts',
                    page: page,
                    sq: sqValue,
                },
                success: function (response) {
                    loading = false;
                    if (JSON.parse(response).postData == null || page == JSON.parse(response).max) {
                        $loader.removeClass('active')
                        $loadMoreButton.hide();
                    }

                    var hml = '';
                    if (JSON.parse(response).postData != null) {
                        JSON.parse(response).postData.forEach(pst => {
                            var yer = ''
                            if (pst.year.length) {

                                yer += '<h6>' + pst.year[0] + '</h6>';

                            }

                            var con = '';

                            if (pst.content != '') {

                                con += '<p class="itl-poster-desc">' +

                                    pst.content +

                                    '</p>';

                            }

                            var rev = ''



                            rev += '<div class="review">'

                            if (pst.rating != '') {

                                rev += '<h6>Rating:' + pst.rating + '</h6>'

                            }

                            if (pst.vote_count != '') {

                                rev += '<h6>Votes <span>' + pst.vote_count + '</span></h6>'

                            }

                            rev += '</div>';
                            hml += '<li>' +
                                '<a href="' + pst.url + '">' +
                                '<div class="itl-q-main">' +
                                '<div class="image">' +
                                '<img src="' + pst.image + '" alt="">' +
                                '</div>' +
                                '<div class="itl-q-content itl-poster-content">' +
                                '<h5>' +
                                pst.title +
                                '</h5>' +
                                yer +
                                con +
                                rev +
                                '</div>' +
                                '</div>' +
                                '</a>' +
                                '</li>'
                            $loader.removeClass('active')


                        });
                        $postContainer.append(hml);

                    }


                    numberOfResult = $('#itlSearchResult').children().length;

                    var total = max_page;
                    $('#totalCount').html(total)
                    $('#currentCount').html(numberOfResult - 1)

                    if (max_page == (numberOfResult - 1)) {
                        $loadMoreButton.hide();
                    } else {
                        $loadMoreButton.show();
                    }

                },

            });

        } else {

            $loadMoreButton.hide();

        }

    });

    function getQueryParameter(parameterName) {

        var queryString = window.location.search.substring(1);

        var queryParams = queryString.split('&');



        for (var i = 0; i < queryParams.length; i++) {

            var pair = queryParams[i].split('=');

            if (pair[0] === parameterName) {

                return pair[1];

            }

        }



        return null;

    }

    // Update Movies Data in the DATABASE
    jQuery(document).on('click', '.itl-button', function (e) {

        jQuery('#itl_loader').css('display', 'block');
        jQuery('.itl-button').attr('disabled', true);
        jQuery.ajax({
            type: 'POST',
            url: itlObj.ajaxurl,
            data: {
                action: 'itl_loop_through_all_movies' // AJAX action defined in the server
                // Add any additional data you want to send
            },
            success: function (response) {
                var res = JSON.parse(response).products_updated;
                if (res.length) {
                    jQuery('#itl_message').html(res.length + ' ' + 'Movies Updated Successfully')
                } else {
                    jQuery('#itl_message').html('All Movies Already Updated!')
                }

                jQuery('#itl_loader').css('display', 'none');
                setTimeout(function () {
                    jQuery('#itl_message').html('');
                }, 9000)
                jQuery('.itl-button').attr('disabled', false);
            },
            error: function (error) {
                console.log(error.responseText);
            }
        });
    })



    // Trailer Play Automatically
    var dtime = 2000;
    var dLTime = 2009;
    jQuery(document).on('mouseenter', '.youId', function (e) {
        jQuery('iframe').remove();
        var target = jQuery(this);
        var tr_id = target.attr('data-trailer-id');
        var playTarget = target.find('.youPlay');

        setTimeout(function () {
            playTarget.append('<iframe width="560" height="315" src="https://www.youtube.com/embed/' + tr_id + '?autoplay=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>');
        }, dtime);
    });

    jQuery(document).on('mouseleave', '.youId', function () {
        var target = jQuery(this);
        var playTarget = target.find('.youPlay');
        setTimeout(function () {
            playTarget.find('iframe').remove();
        }, dLTime);
    });

});




var itlSearchResult = new Vue({
    el: '#itlSearchResult',
    data: {
        postsData: [],
        sortSelected: '',
        sortOrder: '',
        view_detial: true,
        view_grid: false,
        view_compact: false,
        itlLoader: false,
        page: 1,
        maxPage: 1,
        fposts: '',
        searchQuery: ''
    },
    computed: {},
    methods: {
        changeSort() {
            this.createQueries();
        },
        changeSortType(e) {
            if (jQuery('#adv-srch-sort-order').hasClass('active')) {
                jQuery('#adv-srch-sort-order').removeClass('active');
                this.sortOrder = 'DESC'
            } else {
                jQuery('#adv-srch-sort-order').addClass('active');
                this.sortOrder = 'ASC'
            }
            this.createQueries();
        },
        switchLayout(id) {

            switch (id) {
                case 'view_detial':
                    this.view_detial = true;
                    this.view_grid = false;
                    this.view_compact = false;
                    break;
                case 'view_grid':
                    this.view_grid = true;
                    this.view_detial = false;
                    this.view_compact = false;
                    break;
                case 'view_compact':
                    this.view_compact = true;
                    this.view_grid = false;
                    this.view_detial = false;
                    break;
            }
        },
        changeLayout(id) {
            var tid = '#' + id;
            jQuery(".itl-layout button.itl-btn-st-1").removeClass("active");
            jQuery(tid).addClass('active');
            this.switchLayout(id);
        },
        removeWordFromString(inputString, wordToRemove) {
            let wordsArray = inputString.split(',');
            wordsArray = wordsArray.filter(word => word.trim() !== wordToRemove.trim());
            let resultString = wordsArray.join(',');

            return resultString;
        },
        formatSlug(inputString) {
            const words = inputString.split('-');
            const formattedWords = words.map(word => {
                return word.charAt(0).toUpperCase() + word.slice(1);
            });
            const formattedString = formattedWords.join(' ');
            return formattedString;
        },
        loadMoreContent() {
            this.itlLoader = true;
            this.page++;
            this.getData(true);
        },
        refreshDetected() {
            var self = this;
            const currentUrl = window.location.href;
            const hasQueryString = currentUrl.includes('?');
            const urlSearchParams = hasQueryString ? new URLSearchParams(currentUrl.split('?')[1]) : new URLSearchParams();
            var sq = urlSearchParams.get('sq');
            var ss = urlSearchParams.get('ss');
            var so = urlSearchParams.get('so');

            // // Assign the query parameters to data variables
            // this.selectedAllData[0].value = sq || null;

            this.searchQuery = sq;

            var sortSelected = urlSearchParams.get('ss');
            var sortOrder = urlSearchParams.get('so');

            if (sortOrder == '') {
                sortOrder = 'DESC'
            }

            this.sortSelected = sortSelected || '';
            this.sortOrder = sortOrder || 'DESC';
            this.page = 1;
            this.createQueries();

        },
        createQueries() {
            var self = this;
            var searchParams = {}
            if (this.searchQuery != '') {
                searchParams['sq'] = this.searchQuery;
            }
            if (this.sortSelected != null && this.sortSelected != '') {
                searchParams['ss'] = this.sortSelected;
                searchParams['so'] = this.sortOrder;
            }



            var queryString = jQuery.param(searchParams);

            var newUrl = window.location.href.split('?')[0]; // Remove existing query parameters
            newUrl += '?' + queryString;
            // Update the URL
            window.history.pushState({
                path: newUrl
            }, '', newUrl);
            this.page = 1;
            this.getData();
        },
        getData(load = false) {
            var self = this;
            jQuery.ajax({
                url: itlObj.ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'itl_get_search_movies',
                    sq: this.searchQuery,
                    ss: this.sortSelected,
                    so: this.sortOrder,
                    page: this.page,
                },
                success: function (res) {
                    if (!load) {
                        if (res.postData == null) {
                            self.postsData = [];
                        } else {
                            self.postsData = res.postData;
                        }

                    } else {
                        self.itlLoader = false;
                        self.postsData.push(...res.postData);
                    }
                    self.maxPage = res.maxPage;
                    self.fposts = res.fposts;

                },
                error: function (res) {
                    console.log('Error');
                }
            });
        },
    },
    mounted: function () {
        this.refreshDetected();
    }
});