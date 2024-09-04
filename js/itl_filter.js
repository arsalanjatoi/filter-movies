jQuery("#itlItemFilter").attr("style", '');

var itlFilter = new Vue({
    el: '#itlItemFilter',
    data: {
        postsData: [],
        selectedAllData: [{
                key: 'title',
                value: null
            },
            {
                key: 'studio',
                value: null
            },
            {
                key: 'genre',
                value: null
            },
            {
                key: 'tag',
                value: null
            },
            {
                key: 'cast',
                value: null
            },
            {
                key: 'y',
                value: null
            },
            {
                key: 'director',
                value: null
            },
            {
                key: 'network',
                value: null
            },
            {
                key: 'rating',
                value: null
            },
            {
                key: 'awards',
                value: null
            },
            {
                key: 'votes',
                value: null
            },
            {
                key: 'spcontent',
                value: null
            },
            {
                key: 'comment',
                value: null
            },
            {
                key: 'runtime',
                value: null
            },
            {
                key: 'ttype',
                value: null
            },
            {
                key: 'comp',
                value: null
            },
            {
                key: 'certi',
                value: null
            },
            {
                key: 'lang',
                value: null
            },
            {
                key: 'sp',
                value: null
            },
        ],

        minRating: null,
        maxRating: null,

        minVotes: null,
        maxVotes: null,

        minRT: null,
        maxRT: null,

        genres_terms: [],
        ttype_terms: [],
        companies_terms: [],
        certificate_terms: [],
        tag_terms: [],
        awards_terms: [],
        lang_terms: [],
        filteredLang: [],

        sp_terms: [],
        filteredSP: [],

        queryCastString: '',
        castResArr: [],
        choosenCast: [],
        cLoader: false,

        searchQuery: '',
        keywordQuery: '',
        // seeResultDis: false,
        seeResult: false,
        sAllData: [],

        searchResponse: [],

        sortSelected: '',
        sortOrder: '',


        view_detial: true,
        view_grid: false,
        view_compact: false,

        itlLoader: false,
        mainLoader: false,

        page: 1,
        maxPage: 1,
        fposts: '',

        queryLanguage: '',
        checkedNames: [],
        fLangStatus: false,

        querySP: '',
        checkedSP: [],
        fSPStatus: false,

        commentsRes: []
    },
    computed: {
        seeResultDis() {
            return this.selectedAllData.some(itm => {
                return itm.value != null && itm.value != ''
            })
        }

    },
    methods: {
        sResult() {
            this.seeResult = true;
            this.createQueries();
        },
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
        changeLayout(id) {
            var tid = '#' + id;
            jQuery(".itl-layout button.itl-btn-st-1").removeClass("active");
            // if (jQuery(tid).hasClass('active')) {
            //     jQuery(tid).removeClass('active');
            // } else {
            jQuery(tid).addClass('active');
            // }

            this.switchLayout(id);
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
        changeSearchQuery() {
            //empty
        },
        changeTitle(ind) {
            this.createQueries();
        },
        changeStudio(ind) {
            this.createQueries();
        },
        changeDirector(ind) {
            this.createQueries();
        },
        changeNetwork(ind) {
            this.createQueries();
        },
        selectChips(e, g_slug, ind) {
            if (jQuery(e.target).hasClass('active')) {
                jQuery(e.target).removeClass('active');
                var newStr = this.removeWordFromString(this.selectedAllData[ind].value, g_slug);
                this.$set(this.selectedAllData[ind], 'value', newStr);
            } else {
                jQuery(e.target).addClass('active');
                if (this.selectedAllData[ind].value == null || this.selectedAllData[ind].value == '') {
                    this.$set(this.selectedAllData[ind], 'value', g_slug);
                } else {
                    this.selectedAllData[ind].value += ',' + g_slug;
                }
            }
            this.createQueries()
        },
        changeYear(e) {
            this.createQueries();
        },
        changeRating(e, typ) {
            this.selectedAllData[8].value = '';
            if (typ == 'min') {
                this.selectedAllData[8].value = this.minRating;
                if (this.maxRating != null && this.maxRating != '') {
                    this.selectedAllData[8].value += ',' + this.maxRating;
                }
            }
            if (typ == 'max') {
                if (this.minRating != null && this.minRating != '') {
                    this.selectedAllData[8].value = this.minRating;
                } else {
                    this.selectedAllData[8].value = 0;
                }

                this.selectedAllData[8].value += ',' + this.maxRating;
            }
            this.createQueries()
        },
        changeVotes(e, typ) {
            this.selectedAllData[10].value = '';
            if (typ == 'min') {
                this.selectedAllData[10].value = this.minVotes;
                if (this.maxVotes != null && this.maxVotes != '') {
                    this.selectedAllData[10].value += ',' + this.maxVotes;
                }
            }
            if (typ == 'max') {
                if (this.minVotes != null && this.minVotes != '') {
                    this.selectedAllData[10].value = this.minVotes;
                } else {
                    this.selectedAllData[10].value = 0;
                }

                this.selectedAllData[10].value += ',' + this.maxVotes;
            }
            this.createQueries()
        },
        changeRTime(e, typ) {
            this.selectedAllData[13].value = '';
            if (typ == 'min') {
                this.selectedAllData[13].value = this.minRT;
                if (this.maxRT != null && this.maxRT != '') {
                    this.selectedAllData[13].value += ',' + this.maxRT;
                }
            }
            if (typ == 'max') {
                if (this.minRT != null && this.minRT != '') {
                    this.selectedAllData[13].value = this.minRT;
                } else {
                    this.selectedAllData[13].value = 0;
                }

                this.selectedAllData[13].value += ',' + this.maxRT;
            }
            this.createQueries()
        },
        removeLang(lang, typ) {
            var index = null;

            if (typ == 'lang') {
                this.checkedNames.forEach((obj, ind) => {
                    if (obj.slug == lang) {
                        index = ind
                    }
                })
                this.checkedNames.splice(index, 1)

                if (this.checkedNames.length) {

                    var langs = '';
                    this.checkedNames.forEach((c, ind) => {
                        if (ind == 0) {
                            langs = c.slug;
                        } else {
                            langs += ',' + c.slug;
                        }
                    })
                    this.selectedAllData[17].value = langs;
                } else {

                    this.selectedAllData[17].value = null;
                }
            }

            if (typ == 'sp') {
                this.checkedSP.forEach((obj, ind) => {
                    if (obj.slug == lang) {
                        index = ind
                    }
                })
                this.checkedSP.splice(index, 1)
                if (this.checkedSP.length) {
                    var langs = '';
                    this.checkedSP.forEach((c, ind) => {
                        if (ind == 0) {
                            langs = c.slug;
                        } else {
                            langs += ',' + c.slug;
                        }
                    })
                    this.selectedAllData[18].value = langs;
                } else {
                    this.selectedAllData[18].value = null;
                }
            }

            this.createQueries();
        },
        selectCast(cast, typ = 0) {
            if (typ == 'rem') {
                var index = null;
                this.choosenCast.forEach((obj, ind) => {
                    if (obj.slug == cast) {
                        index = ind
                    }
                })
                this.choosenCast.splice(index, 1)
            } else {
                if (this.choosenCast.includes(cast)) {

                } else {
                    this.choosenCast.push(cast);
                }
            }

            this.castResArr = [];

            if (this.choosenCast.length) {
                var casts = '';
                this.choosenCast.forEach((c, ind) => {
                    if (ind == 0) {
                        casts = c.slug;
                    } else {
                        casts += ',' + c.slug;
                    }
                })
                this.selectedAllData[4].value = casts;
            } else {
                this.selectedAllData[4].value = null;
            }
            this.createQueries();
        },
        removeWordFromString(inputString, wordToRemove) {
            let wordsArray = inputString.split(',');
            wordsArray = wordsArray.filter(word => word.trim() !== wordToRemove.trim());
            let resultString = wordsArray.join(',');

            return resultString;
        },
        removeItem(sa) {
            var self = this;
            this.selectedAllData.forEach((param, pind) => {
                if (param.value !== null && param.value !== '') {
                    if (param.key != 'rating' && param.key != 'votes' && param.key != 'runtime' && param.value.includes(',')) {
                        let arr = param.value.split(',');
                        var v = '';
                        arr.forEach((itm, iz) => {
                            if (sa.value != itm) {
                                v += (v == '') ? itm : (',' + itm);
                            }
                        })
                        // self.selectedAllData[param.key] = v;
                        self.$set(self.selectedAllData[pind], 'value', v);
                    } else {
                        if (param.key == 'rating' && sa.key == 'rating') {
                            self.$set(self.selectedAllData[pind], 'value', null);
                        }
                        if (param.key == 'votes' && sa.key == 'votes') {
                            self.$set(self.selectedAllData[pind], 'value', null);
                        }

                        if (param.key == 'runtime' && sa.key == 'runtime') {
                            self.$set(self.selectedAllData[pind], 'value', null);
                        }
                        if (sa.value == param.value) {
                            // self.selectedAllData[pind][param.key] = null;
                            self.$set(self.selectedAllData[pind], 'value', null);
                        }
                    }
                }
            });
            if (sa.key == 'cast') {
                self.choosenCast.forEach((cc, cind) => {
                    if (cc.slug == sa.value) {
                        self.choosenCast.splice(cind, 1)
                    }
                })
            }

            if (sa.key == 'lang') {
                self.checkedNames.forEach((cc, cind) => {
                    if (cc.slug == sa.value) {
                        self.checkedNames.splice(cind, 1)
                    }
                })
            }

            if (sa.key == 'sp') {
                self.checkedSP.forEach((cc, cind) => {
                    if (cc.slug == sa.value) {
                        self.checkedSP.splice(cind, 1)
                    }
                })
            }
            this.createQueries();
        },
        createQueries() {
            var self = this;
            this.page = 1;
            this.sAllData = []
            var searchParams = {}

            this.selectedAllData.forEach(param => {
                if (param.value !== null && param.value !== '') {
                    searchParams[param.key] = param.value;
                    // self.sAllData[param.key] = param.value;
                    if (param.key != 'rating' && param.key != 'votes' && param.key != 'runtime' && param.value.includes(',')) {
                        let arr = param.value.split(',');
                        arr.forEach(itm => {
                            var obj = {
                                key: param.key,
                                value: itm,
                            }
                            self.sAllData.push(obj);
                        })
                    } else {
                        var ob = {
                            key: param.key,
                            value: param.value,
                        }
                        self.sAllData.push(ob);
                    }
                }
            });




            if (this.sortSelected != null && this.sortSelected != '') {
                searchParams['ss'] = this.sortSelected;
                searchParams['so'] = this.sortOrder;
            }

            // this.sAllData.push(searchParams);
            // this.$set(, 'value', searchParams);
            var queryString = jQuery.param(searchParams);

            var newUrl = window.location.href.split('?')[0]; // Remove existing query parameters
            newUrl += '?' + queryString;
            // Update the URL
            window.history.pushState({
                path: newUrl
            }, '', newUrl);
            if (this.seeResult) {
                this.mainLoader = true;
                this.getData();
            }

        },
        getData(load = false) {
            var self = this;
            jQuery.ajax({
                url: itlObj.ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'get_movies_data',
                    title: this.selectedAllData[0].value,
                    studio: this.selectedAllData[1].value,
                    genre: this.selectedAllData[2].value,
                    tag: this.selectedAllData[3].value,
                    cast: this.selectedAllData[4].value,
                    year: this.selectedAllData[5].value,
                    director: this.selectedAllData[6].value,
                    network: this.selectedAllData[7].value,
                    rating: this.selectedAllData[8].value,
                    awards: this.selectedAllData[9].value,
                    votes: this.selectedAllData[10].value,
                    spcontent: this.selectedAllData[11].value,
                    comment: this.selectedAllData[12].value,
                    runtime: this.selectedAllData[13].value,
                    ttype: this.selectedAllData[14].value,
                    companies: this.selectedAllData[15].value,
                    certificates: this.selectedAllData[16].value,
                    languages: this.selectedAllData[17].value,
                    sp: this.selectedAllData[18].value,
                    sort: this.sortSelected,
                    sort_order: this.sortOrder,
                    page: this.page,
                },
                success: function (res) {
                    if (!load) {
                        if (res.postData == null) {
                            self.postsData = [];
                        } else {
                            self.postsData = res.postData;
                        }
                        self.mainLoader = false;

                    } else {
                        self.itlLoader = false;
                        self.postsData.push(...res.postData);
                    }
                    self.fposts = res.fposts;
                    self.maxPage = res.maxPage;

                },
                error: function (res) {
                    console.log('Error');
                }
            });
        },
        getFieldsData() {
            var self = this;
            jQuery.ajax({
                url: itlObj.ajaxurl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'get_fields_data',
                },
                success: function (res) {

                    self.genres_terms = res.genres;
                    self.awards_terms = res.awards;
                    self.tag_terms = res.tags;
                    self.ttype_terms = res.ttype;
                    self.companies_terms = res.companies;
                    self.certificate_terms = res.certificates;
                    self.lang_terms = res.lang;
                    self.filteredLang = res.lang;
                    self.sp_terms = res.sp;
                    self.filteredSP = res.sp;
                },
                error: function (res) {
                    console.log('Error');
                }
            });
        },
        getCastData() {
            this.cLoader = true;
            if (this.timer) {
                clearTimeout(this.timer);
            }
            this.timer = setTimeout(() => {
                this.fetchData('cast');
            }, 500);
        },
        getSearchData() {
            this.cLoader = true;
            if (this.timer) {
                clearTimeout(this.timer);
            }
            this.timer = setTimeout(() => {
                this.fetchData('search');
            }, 500);
        },
        getComments() {
            this.cLoader = true;
            if (this.timer) {
                clearTimeout(this.timer);
            }
            this.timer = setTimeout(() => {
                this.fetchComments();
            }, 1100);
        },
        fetchComments() {
            var self = this;
            if (self.selectedAllData[12].value == '#') {
                jQuery.ajax({
                    url: itlObj.ajaxurl,
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'itl_get_hash_comments',
                        data: self.selectedAllData[12].value,
                    },
                    success: function (res) {
                        console.log(res);
                        if (res.data != null) {
                            self.commentsRes = res.data;
                        } else {
                            self.commentsRes = [];
                        }
                        self.cLoader = false;
                    },
                    error: function (res) {
                        console.log('Error');
                    }
                });
            } else {
                self.commentsRes = [];
                self.cLoader = false;
                if (this.ctimer) {
                    clearTimeout(this.ctimer);
                }
                this.ctimer = setTimeout(() => {
                    this.createQueries();
                }, 500);
            }
        },
        getTheComment(comment) {
            var self = this;
            self.selectedAllData[12].value = comment;
            self.commentsRes = [];
            if (this.ff) {
                clearTimeout(this.ff);
            }
            this.ff = setTimeout(() => {
                this.createQueries();
            }, 150);

        },
        fetchData(nm) {
            const self = this;
            if (nm == 'cast') {
                if (self.queryCastString) {
                    jQuery.ajax({
                        url: itlObj.ajaxurl,
                        type: 'POST',
                        dataType: 'JSON',
                        data: {
                            action: 'get_cast_data',
                            data: self.queryCastString,
                        },
                        success: function (res) {
                            console.log(res);

                            // Assuming the response is an array of objects with 'name' property
                            self.castResArr = res.castTerms;
                            self.cLoader = false;

                        },
                        error: function (res) {
                            console.log('Error');
                        }
                    });
                } else {
                    self.castResArr = [];
                    self.cLoader = false;
                }

            }

            if (nm == 'search') {
                if (self.searchQuery) {
                    jQuery.ajax({
                        url: itlObj.ajaxurl,
                        type: 'POST',
                        dataType: 'JSON',
                        data: {
                            action: 'get_search_data',
                            data: self.searchQuery,
                        },
                        success: function (res) {
                            console.log(res);
                            if (res.data != null) {
                                self.searchResponse = res.data;
                            } else {
                                self.searchResponse = [];
                            }

                            self.cLoader = false;
                        },
                        error: function (res) {
                            console.log('Error');
                        }
                    });
                } else {
                    self.searchResponse = [];
                    self.cLoader = false;
                }

            }

        },
        formatSlug(inputString) {
            const words = inputString.split('-');
            const formattedWords = words.map(word => {
                return word.charAt(0).toUpperCase() + word.slice(1);
            });
            const formattedString = formattedWords.join(' ');
            return formattedString;
        },
        refreshDetected() {
            var self = this;
            const currentUrl = window.location.href;
            const hasQueryString = currentUrl.includes('?');
            const urlSearchParams = hasQueryString ? new URLSearchParams(currentUrl.split('?')[1]) : new URLSearchParams();
            var title = urlSearchParams.get('title');
            var studio = urlSearchParams.get('studio');
            var genre = urlSearchParams.get('genre');
            var tag = urlSearchParams.get('tag');
            var cast = urlSearchParams.get('cast');
            var year = urlSearchParams.get('y');
            var director = urlSearchParams.get('director');
            var network = urlSearchParams.get('network');
            var rating = urlSearchParams.get('rating');
            var awards = urlSearchParams.get('awards');
            var votes = urlSearchParams.get('votes');
            var spcontent = urlSearchParams.get('spcontent');
            var comment = urlSearchParams.get('comment');
            var runtime = urlSearchParams.get('runtime');
            var ttype = urlSearchParams.get('ttype');
            var comp = urlSearchParams.get('comp');
            var certi = urlSearchParams.get('certi');
            var lang = urlSearchParams.get('lang');
            var sp = urlSearchParams.get('sp');
            // Assign the query parameters to data variables
            this.selectedAllData[0].value = title || null;
            this.selectedAllData[1].value = studio || null;
            this.selectedAllData[2].value = genre || null;
            this.selectedAllData[3].value = tag || null;
            this.selectedAllData[4].value = cast || null;
            this.selectedAllData[5].value = year || null;
            this.selectedAllData[6].value = director || null;
            this.selectedAllData[7].value = network || null;
            this.selectedAllData[8].value = rating || null;
            this.selectedAllData[9].value = awards || null;
            this.selectedAllData[10].value = votes || null;
            this.selectedAllData[11].value = spcontent || null;
            this.selectedAllData[12].value = comment || null;
            this.selectedAllData[13].value = runtime || null;
            this.selectedAllData[14].value = ttype || null;
            this.selectedAllData[15].value = comp || null;
            this.selectedAllData[16].value = certi || null;
            this.selectedAllData[17].value = lang || null;
            this.selectedAllData[18].value = sp || null;

            if (cast != null) {
                if (cast.includes(',')) {
                    let arr = cast.split(',');
                    arr.forEach(itm => {
                        var name = self.formatSlug(itm);
                        var obj = {
                            name: name,
                            slug: itm,
                        }
                        self.choosenCast.push(obj);
                    })
                } else {
                    var name = self.formatSlug(cast);
                    var obj = {
                        name: name,
                        slug: cast,
                    }
                    self.choosenCast.push(obj);
                }
            }


            if (lang != null) {
                if (lang.includes(',')) {
                    let arr = lang.split(',');
                    arr.forEach(itm => {
                        var name = self.formatSlug(itm);
                        var obj = {
                            name: name,
                            slug: itm,
                        }
                        self.checkedNames.push(obj);
                    })
                } else {
                    var name = self.formatSlug(lang);
                    var obj = {
                        name: name,
                        slug: lang,
                    }
                    self.checkedNames.push(obj);
                }
            }

            if (sp != null) {
                if (sp.includes(',')) {
                    let arr = sp.split(',');
                    arr.forEach(itm => {
                        var name = self.formatSlug(itm);
                        var obj = {
                            name: name,
                            slug: itm,
                        }
                        self.checkedSP.push(obj);
                    })
                } else {
                    var name = self.formatSlug(lang);
                    var obj = {
                        name: name,
                        slug: lang,
                    }
                    self.checkedSP.push(obj);
                }
            }


            this.seeResult = this.selectedAllData.some(itm => itm.value != null)

            var sortSelected = urlSearchParams.get('ss');
            var sortOrder = urlSearchParams.get('so');

            if (sortOrder == '') {
                sortOrder = 'DESC'
            }

            this.sortSelected = sortSelected || '';
            this.sortOrder = sortOrder || 'DESC';

            this.createQueries();
        },
        loadMoreContent() {
            this.itlLoader = true;
            this.page++;
            this.getData(true);
        },
        getLanguageData() {
            const filtered = this.lang_terms.filter((itm) => {
                return itm.name.toLowerCase().includes(this.queryLanguage.toLowerCase());
            });
            this.filteredLang = this.queryLanguage.trim() === '' ? this.lang_terms : filtered;
        },
        getSPData() {
            const filtered = this.sp_terms.filter((itm) => {
                return itm.name.toLowerCase().includes(this.querySP.toLowerCase());
            });
            this.filteredSP = this.querySP.trim() === '' ? this.sp_terms : filtered;
        },
        handleFLanguage() {
            this.fLangStatus = true
            this.fSPStatus = false
        },
        handleSP() {
            this.fSPStatus = true
            this.fLangStatus = false
        },
        hideDiv() {
            // this.$nextTick(() => {
            //     console.log(document.activeElement)
            //     const langList = document.getElementById('langList');
            //     // Check if the related target (where the click occurred) is not within the input or the specific div
            //     if (!langList.contains(document.activeElement)) {
            //         this.fLangStatus = false;
            //     }
            // });
        },
        // handleClickOutside(event) {
        //     // Check if the click occurred outside the mainDiv
        //     const langList = document.getElementById('langList');
        //     if (langList && !langList.contains(event.target)) {
        //         this.fLangStatus = false;
        //     }
        // },
        changeLang(ln, typ) {
            var index = null;
            if (typ == 'lang') {
                this.checkedNames.forEach((obj, ind) => {
                    if (obj.slug == ln.slug) {
                        index = ind
                    }
                })
                if (index != null) {
                    this.checkedNames.splice(index, 1)
                } else {
                    this.checkedNames.push(ln);
                }

                if (this.checkedNames.length) {

                    var langs = '';
                    this.checkedNames.forEach((c, ind) => {
                        if (ind == 0) {
                            langs = c.slug;
                        } else {
                            langs += ',' + c.slug;
                        }
                    })
                    this.selectedAllData[17].value = langs;
                } else {

                    this.selectedAllData[17].value = null;
                }
            }


            if (typ == 'sp') {

                this.checkedSP.forEach((obj, ind) => {
                    if (obj.slug == ln.slug) {
                        index = ind
                    }
                })
                if (index != null) {
                    this.checkedSP.splice(index, 1)
                } else {
                    this.checkedSP.push(ln);
                }

                if (this.checkedSP.length) {

                    var langs = '';
                    this.checkedSP.forEach((c, ind) => {
                        if (ind == 0) {
                            langs = c.slug;
                        } else {
                            langs += ',' + c.slug;
                        }
                    })
                    this.selectedAllData[18].value = langs;
                } else {

                    this.selectedAllData[18].value = null;
                }

            }

            this.createQueries();
        },

    },
    mounted: function () {
        console.log("cache cleared! 0");
        this.getFieldsData();
        this.refreshDetected();
    }
});



// jQuery click function
jQuery(document).ready(function () {
    // Select the button by its ID
    jQuery("a.itl-toggle-bars").click(function (e) {
        e.preventDefault();
        jQuery(this).toggleClass('itlshow')

        if (jQuery(this).hasClass('itlshow')) {
            jQuery(".accordion").each(function () {
                if (!jQuery(this).hasClass('active')) {
                    jQuery(this).addClass('active');
                    jQuery(".panel").each(function () {
                        jQuery(this).css('display', 'block')
                    })
                }
            });
        } else {
            jQuery(".accordion").each(function () {
                if (jQuery(this).hasClass('active')) {
                    jQuery(this).removeClass('active');
                    jQuery(".panel").each(function () {
                        jQuery(this).css('display', 'none')
                    })
                }
            });
        }
    });


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


var acc = document.getElementsByClassName("accordion");
var i;

for (i = 0; i < acc.length; i++) {
    acc[i].addEventListener("click", function () {
        /* Toggle between adding and removing the "active" class,
        to highlight the button that controls the panel */
        this.classList.toggle("active");

        /* Toggle between hiding and showing the active panel */
        var panel = this.nextElementSibling;
        if (panel.style.display === "block") {
            panel.style.display = "none";
        } else {
            panel.style.display = "block";
        }
    });
}



document.addEventListener('click', function (event) {
    // console.log(event.target.classList)
    if (!event.target.classList.contains('inside')) {
        itlFilter.fLangStatus = false;
        itlFilter.fSPStatus = false;
    }
});
// Load More Button