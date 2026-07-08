/**
 * International Telephone Input – Vanilla JS
 *
 * Lightweight, jQuery-free replacement for intlTelInput v17.
 * Reuses the existing flags.png sprite sheet and phone-picker.css
 * (`.iti__*` class naming convention).
 *
 * Usage:
 *   var instance = window.intlTelInputVanilla(inputElement, {
 *       initialCountry: 'auto',
 *       geoIpLookup: function(callback){ ... callback('US'); },
 *       flagsUrl: '/path/to/flags.png',
 *       flagsRetinaUrl: '/path/to/flags@2x.png',
 *       placeholderNumberType: 'MOBILE'
 *   });
 *
 *   instance.getNumber()               // → '+14155552671'
 *   instance.getSelectedCountryData()  // → { name, iso2, dialCode, ... }
 *   instance.isValidNumber()           // → true/false
 *   instance.getValidationError()      // → 0–5 error code
 *   instance.destroy()
 *
 * @package wp2fa
 * @since   4.0.0
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Country data (same as intlTelInput v17)                             */
	/* [Name, iso2, dialCode, priority?, areaCodes?]                       */
	/* ------------------------------------------------------------------ */
	var rawCountries = [
		["Afghanistan (‫افغانستان‬‎)","af","93"],["Albania (Shqipëri)","al","355"],["Algeria (‫الجزائر‬‎)","dz","213"],["American Samoa","as","1",5,["684"]],["Andorra","ad","376"],["Angola","ao","244"],["Anguilla","ai","1",6,["264"]],["Antigua and Barbuda","ag","1",7,["268"]],["Argentina","ar","54"],["Armenia (Հայաստան)","am","374"],["Aruba","aw","297"],["Ascension Island","ac","247"],["Australia","au","61",0],["Austria (Österreich)","at","43"],["Azerbaijan (Azərbaycan)","az","994"],["Bahamas","bs","1",8,["242"]],["Bahrain (‫البحرين‬‎)","bh","973"],["Bangladesh (বাংলাদেশ)","bd","880"],["Barbados","bb","1",9,["246"]],["Belarus (Беларусь)","by","375"],["Belgium (België)","be","32"],["Belize","bz","501"],["Benin (Bénin)","bj","229"],["Bermuda","bm","1",10,["441"]],["Bhutan (འབྲུག)","bt","975"],["Bolivia","bo","591"],["Bosnia and Herzegovina (Босна и Херцеговина)","ba","387"],["Botswana","bw","267"],["Brazil (Brasil)","br","55"],["British Indian Ocean Territory","io","246"],["British Virgin Islands","vg","1",11,["284"]],["Brunei","bn","673"],["Bulgaria (България)","bg","359"],["Burkina Faso","bf","226"],["Burundi (Uburundi)","bi","257"],["Cambodia (កម្ពុជា)","kh","855"],["Cameroon (Cameroun)","cm","237"],["Canada","ca","1",1,["204","226","236","249","250","289","306","343","365","387","403","416","418","431","437","438","450","506","514","519","548","579","581","587","604","613","639","647","672","705","709","742","778","780","782","807","819","825","867","873","902","905"]],["Cape Verde (Kabu Verdi)","cv","238"],["Caribbean Netherlands","bq","599",1,["3","4","7"]],["Cayman Islands","ky","1",12,["345"]],["Central African Republic (République centrafricaine)","cf","236"],["Chad (Tchad)","td","235"],["Chile","cl","56"],["China (中国)","cn","86"],["Christmas Island","cx","61",2,["89164"]],["Cocos (Keeling) Islands","cc","61",1,["89162"]],["Colombia","co","57"],["Comoros (‫جزر القمر‬‎)","km","269"],["Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)","cd","243"],["Congo (Republic) (Congo-Brazzaville)","cg","242"],["Cook Islands","ck","682"],["Costa Rica","cr","506"],["Côte d'Ivoire","ci","225"],["Croatia (Hrvatska)","hr","385"],["Cuba","cu","53"],["Curaçao","cw","599",0],["Cyprus (Κύπρος)","cy","357"],["Czech Republic (Česká republika)","cz","420"],["Denmark (Danmark)","dk","45"],["Djibouti","dj","253"],["Dominica","dm","1",13,["767"]],["Dominican Republic (República Dominicana)","do","1",2,["809","829","849"]],["Ecuador","ec","593"],["Egypt (‫مصر‬‎)","eg","20"],["El Salvador","sv","503"],["Equatorial Guinea (Guinea Ecuatorial)","gq","240"],["Eritrea","er","291"],["Estonia (Eesti)","ee","372"],["Eswatini","sz","268"],["Ethiopia","et","251"],["Falkland Islands (Islas Malvinas)","fk","500"],["Faroe Islands (Føroyar)","fo","298"],["Fiji","fj","679"],["Finland (Suomi)","fi","358",0],["France","fr","33"],["French Guiana (Guyane française)","gf","594"],["French Polynesia (Polynésie française)","pf","689"],["Gabon","ga","241"],["Gambia","gm","220"],["Georgia (საქართველო)","ge","995"],["Germany (Deutschland)","de","49"],["Ghana (Gaana)","gh","233"],["Gibraltar","gi","350"],["Greece (Ελλάδα)","gr","30"],["Greenland (Kalaallit Nunaat)","gl","299"],["Grenada","gd","1",14,["473"]],["Guadeloupe","gp","590",0],["Guam","gu","1",15,["671"]],["Guatemala","gt","502"],["Guernsey","gg","44",1,["1481","7781","7839","7911"]],["Guinea (Guinée)","gn","224"],["Guinea-Bissau (Guiné Bissau)","gw","245"],["Guyana","gy","592"],["Haiti","ht","509"],["Honduras","hn","504"],["Hong Kong (香港)","hk","852"],["Hungary (Magyarország)","hu","36"],["Iceland (Ísland)","is","354"],["India (भारत)","in","91"],["Indonesia","id","62"],["Iran (‫ایران‬‎)","ir","98"],["Iraq (‫العراق‬‎)","iq","964"],["Ireland","ie","353"],["Isle of Man","im","44",2,["1624","74576","7524","7924","7624"]],["Israel (‫ישראל‬‎)","il","972"],["Italy (Italia)","it","39",0],["Jamaica","jm","1",4,["876","658"]],["Japan (日本)","jp","81"],["Jersey","je","44",3,["1534","7509","7700","7797","7829","7937"]],["Jordan (‫الأردن‬‎)","jo","962"],["Kazakhstan (Казахстан)","kz","7",1,["33","7"]],["Kenya","ke","254"],["Kiribati","ki","686"],["Kosovo","xk","383"],["Kuwait (‫الكويت‬‎)","kw","965"],["Kyrgyzstan (Кыргызстан)","kg","996"],["Laos (ລາວ)","la","856"],["Latvia (Latvija)","lv","371"],["Lebanon (‫لبنان‬‎)","lb","961"],["Lesotho","ls","266"],["Liberia","lr","231"],["Libya (‫ليبيا‬‎)","ly","218"],["Liechtenstein","li","423"],["Lithuania (Lietuva)","lt","370"],["Luxembourg","lu","352"],["Macau (澳門)","mo","853"],["North Macedonia (Македонија)","mk","389"],["Madagascar (Madagasikara)","mg","261"],["Malawi","mw","265"],["Malaysia","my","60"],["Maldives","mv","960"],["Mali","ml","223"],["Malta","mt","356"],["Marshall Islands","mh","692"],["Martinique","mq","596"],["Mauritania (‫موريتانيا‬‎)","mr","222"],["Mauritius (Moris)","mu","230"],["Mayotte","yt","262",1,["269","639"]],["Mexico (México)","mx","52"],["Micronesia","fm","691"],["Moldova (Republica Moldova)","md","373"],["Monaco","mc","377"],["Mongolia (Монгол)","mn","976"],["Montenegro (Crna Gora)","me","382"],["Montserrat","ms","1",16,["664"]],["Morocco (‫المغرب‬‎)","ma","212",0],["Mozambique (Moçambique)","mz","258"],["Myanmar (Burma) (မြန်မာ)","mm","95"],["Namibia (Namibië)","na","264"],["Nauru","nr","674"],["Nepal (नेपाल)","np","977"],["Netherlands (Nederland)","nl","31"],["New Caledonia (Nouvelle-Calédonie)","nc","687"],["New Zealand","nz","64"],["Nicaragua","ni","505"],["Niger (Nijar)","ne","227"],["Nigeria","ng","234"],["Niue","nu","683"],["Norfolk Island","nf","672"],["North Korea (조선 민주주의 인민 공화국)","kp","850"],["Northern Mariana Islands","mp","1",17,["670"]],["Norway (Norge)","no","47",0],["Oman (‫عُمان‬‎)","om","968"],["Pakistan (‫پاکستان‬‎)","pk","92"],["Palau","pw","680"],["Palestine (‫فلسطين‬‎)","ps","970"],["Panama (Panamá)","pa","507"],["Papua New Guinea","pg","675"],["Paraguay","py","595"],["Peru (Perú)","pe","51"],["Philippines","ph","63"],["Poland (Polska)","pl","48"],["Portugal","pt","351"],["Puerto Rico","pr","1",3,["787","939"]],["Qatar (‫قطر‬‎)","qa","974"],["Réunion (La Réunion)","re","262",0],["Romania (România)","ro","40"],["Russia (Россия)","ru","7",0],["Rwanda","rw","250"],["Saint Barthélemy","bl","590",1],["Saint Helena","sh","290"],["Saint Kitts and Nevis","kn","1",18,["869"]],["Saint Lucia","lc","1",19,["758"]],["Saint Martin (Saint-Martin (partie française))","mf","590",2],["Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)","pm","508"],["Saint Vincent and the Grenadines","vc","1",20,["784"]],["Samoa","ws","685"],["San Marino","sm","378"],["São Tomé and Príncipe (São Tomé e Príncipe)","st","239"],["Saudi Arabia (‫المملكة العربية السعودية‬‎)","sa","966"],["Senegal (Sénégal)","sn","221"],["Serbia (Србија)","rs","381"],["Seychelles","sc","248"],["Sierra Leone","sl","232"],["Singapore","sg","65"],["Sint Maarten","sx","1",21,["721"]],["Slovakia (Slovensko)","sk","421"],["Slovenia (Slovenija)","si","386"],["Solomon Islands","sb","677"],["Somalia (Soomaaliya)","so","252"],["South Africa","za","27"],["South Korea (대한민국)","kr","82"],["South Sudan (‫جنوب السودان‬‎)","ss","211"],["Spain (España)","es","34"],["Sri Lanka (ශ්‍රී ලංකාව)","lk","94"],["Sudan (‫السودان‬‎)","sd","249"],["Suriname","sr","597"],["Svalbard and Jan Mayen","sj","47",1,["79"]],["Sweden (Sverige)","se","46"],["Switzerland (Schweiz)","ch","41"],["Syria (‫سوريا‬‎)","sy","963"],["Taiwan (台灣)","tw","886"],["Tajikistan","tj","992"],["Tanzania","tz","255"],["Thailand (ไทย)","th","66"],["Timor-Leste","tl","670"],["Togo","tg","228"],["Tokelau","tk","690"],["Tonga","to","676"],["Trinidad and Tobago","tt","1",22,["868"]],["Tunisia (‫تونس‬‎)","tn","216"],["Turkey (Türkiye)","tr","90"],["Turkmenistan","tm","993"],["Turks and Caicos Islands","tc","1",23,["649"]],["Tuvalu","tv","688"],["U.S. Virgin Islands","vi","1",24,["340"]],["Uganda","ug","256"],["Ukraine (Україна)","ua","380"],["United Arab Emirates (‫الإمارات العربية المتحدة‬‎)","ae","971"],["United Kingdom","gb","44",0],["United States","us","1",0],["Uruguay","uy","598"],["Uzbekistan (Oʻzbekiston)","uz","998"],["Vanuatu","vu","678"],["Vatican City (Città del Vaticano)","va","39",1,["06698"]],["Venezuela","ve","58"],["Vietnam (Việt Nam)","vn","84"],["Wallis and Futuna (Wallis-et-Futuna)","wf","681"],["Western Sahara (‫الصحراء الغربية‬‎)","eh","212",1,["5288","5289"]],["Yemen (‫اليمن‬‎)","ye","967"],["Zambia","zm","260"],["Zimbabwe","zw","263"],["Åland Islands","ax","358",1,["18"]]
	];

	var allCountries = rawCountries.map(function (c) {
		return {
			name: c[0],
			iso2: c[1],
			dialCode: c[2],
			priority: c[3] || 0,
			areaCodes: c[4] || null
		};
	});

	/* Preferred countries shown at top of dropdown. */
	var defaultPreferred = ['us', 'gb'];

	/* ------------------------------------------------------------------ */
	/* Validation error codes (compatible with intlTelInput utils)         */
	/* ------------------------------------------------------------------ */
	var validationErrors = {
		IS_POSSIBLE: 0,
		INVALID_COUNTRY_CODE: 1,
		TOO_SHORT: 2,
		TOO_LONG: 3,
		NOT_A_NUMBER: 4,
		INVALID_LENGTH: 5
	};

	/* ------------------------------------------------------------------ */
	/* Typical phone-number length ranges per dial code.                   */
	/* This enables basic validation without loading the full              */
	/* libphonenumber utils bundle.                                        */
	/* ------------------------------------------------------------------ */
	var phoneLengthRanges = {
		'1':[10,10],'7':[10,10],'20':[10,10],'27':[9,9],'30':[10,10],
		'31':[9,9],'32':[8,9],'33':[9,9],'34':[9,9],'36':[8,9],
		'39':[9,11],'40':[9,9],'41':[9,9],'43':[4,13],'44':[10,10],
		'45':[8,8],'46':[7,13],'47':[8,8],'48':[9,9],'49':[3,13],
		'51':[8,9],'52':[10,10],'53':[8,8],'54':[6,11],'55':[10,11],
		'56':[8,9],'57':[10,10],'58':[10,10],'60':[7,10],'61':[9,9],
		'62':[5,12],'63':[10,10],'64':[8,10],'65':[8,8],'66':[9,9],
		'81':[9,10],'82':[8,11],'84':[9,10],'86':[11,11],'90':[10,10],
		'91':[10,10],'92':[10,10],'93':[9,9],'94':[9,9],'95':[7,10],
		'98':[10,10],'211':[9,9],'212':[9,9],'213':[9,9],'216':[8,8],
		'218':[9,10],'220':[7,7],'221':[9,9],'222':[8,8],'223':[8,8],
		'224':[8,9],'225':[10,10],'226':[8,8],'227':[8,8],'228':[8,8],
		'229':[8,8],'230':[7,8],'231':[7,8],'232':[8,8],'233':[9,9],
		'234':[7,10],'235':[8,8],'236':[8,8],'237':[9,9],'238':[7,7],
		'239':[7,7],'240':[9,9],'241':[7,8],'242':[9,9],'243':[9,9],
		'244':[9,9],'245':[7,7],'246':[7,7],'247':[4,4],'248':[7,7],
		'249':[9,9],'250':[9,9],'251':[9,9],'252':[7,8],'253':[8,8],
		'254':[9,10],'255':[9,9],'256':[9,9],'257':[8,8],'258':[8,9],
		'260':[9,9],'261':[9,10],'262':[9,9],'263':[9,9],'264':[7,10],
		'265':[7,9],'266':[8,8],'267':[7,8],'268':[7,8],'269':[7,7],
		'290':[4,4],'291':[7,7],'297':[7,7],'298':[6,6],'299':[6,6],
		'350':[8,8],'351':[9,9],'352':[4,11],'353':[7,9],'354':[7,9],
		'355':[8,9],'356':[8,8],'357':[8,8],'358':[5,12],'359':[7,9],
		'370':[8,8],'371':[8,8],'372':[7,8],'373':[8,8],'374':[8,8],
		'375':[9,10],'376':[6,9],'377':[8,9],'378':[6,10],'380':[9,9],
		'381':[8,9],'382':[8,8],'383':[8,8],'385':[8,9],'386':[8,8],
		'387':[8,8],'389':[8,8],'420':[9,9],'421':[9,9],'423':[7,9],
		'500':[5,5],'501':[7,7],'502':[8,8],'503':[8,8],'504':[8,8],
		'505':[8,8],'506':[8,8],'507':[7,8],'508':[6,6],'509':[8,8],
		'590':[9,9],'591':[8,8],'592':[7,7],'593':[8,9],'594':[9,9],
		'595':[9,9],'596':[9,9],'597':[6,7],'598':[8,8],'599':[7,7],
		'670':[7,8],'672':[6,6],'673':[7,7],'674':[7,7],'675':[8,8],
		'676':[5,7],'677':[7,7],'678':[5,7],'679':[7,7],'680':[7,7],
		'681':[6,6],'682':[5,5],'683':[4,4],'685':[5,7],'686':[8,8],
		'687':[6,6],'688':[5,6],'689':[6,6],'690':[4,4],'691':[7,7],
		'692':[7,7],'850':[8,12],'852':[8,8],'853':[8,8],'855':[8,9],
		'856':[8,10],'870':[9,9],'880':[10,10],'886':[9,9],'960':[7,7],
		'961':[7,8],'962':[8,9],'963':[7,9],'964':[10,10],'965':[8,8],
		'966':[9,9],'967':[7,9],'968':[8,8],'970':[9,9],'971':[7,9],
		'972':[9,9],'973':[8,8],'974':[7,8],'975':[8,8],'976':[8,8],
		'977':[10,10],'992':[9,9],'993':[8,8],'994':[9,9],'995':[9,9],
		'996':[9,9],'998':[9,9]
	};

	/* ------------------------------------------------------------------ */
	/* Helper: build a dial-code → country lookup.                         */
	/* ------------------------------------------------------------------ */
	var dialCodeMap = {};
	allCountries.forEach(function (c) {
		if (!dialCodeMap[c.dialCode]) {
			dialCodeMap[c.dialCode] = [];
		}
		dialCodeMap[c.dialCode].push(c);
	});

	/* ------------------------------------------------------------------ */
	/* IntlTelInputVanilla constructor                                     */
	/* ------------------------------------------------------------------ */
	function IntlTelInputVanilla(telInput, opts) {
		this.telInput = telInput;
		this.options = Object.assign({
			initialCountry: '',
			geoIpLookup: null,
			placeholderNumberType: 'MOBILE',
			preferredCountries: defaultPreferred,
			allowDropdown: true,
			autoHideDialCode: true,
			nationalMode: true,
			formatOnDisplay: true
		}, opts || {});

		this.selectedCountry = null;
		this._boundDocClick = null;
		this._built = false;

		this._init();
	}

	IntlTelInputVanilla.prototype._init = function () {
		this._buildUI();
		this._bindEvents();

		var self = this;
		var initial = (this.options.initialCountry || '').toLowerCase();

		if (initial === 'auto' && typeof this.options.geoIpLookup === 'function') {
			this.options.geoIpLookup(function (countryCode) {
				self._setCountry((countryCode || 'us').toLowerCase());
			});
		} else {
			this._setCountry(initial || 'us');
		}
	};

	/* ------------------------------------------------------------------ */
	/* Build the DOM structure around the <input>.                         */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.prototype._buildUI = function () {
		if (this._built) return;
		this._built = true;

		var input = this.telInput;

		/* Wrapper */
		var wrap = document.createElement('div');
		wrap.className = 'iti iti--allow-dropdown';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);
		this.wrap = wrap;

		/* Flag container (left of input) */
		var flagContainer = document.createElement('div');
		flagContainer.className = 'iti__flag-container';
		wrap.insertBefore(flagContainer, input);

		var selectedFlag = document.createElement('div');
		selectedFlag.className = 'iti__selected-flag';
		selectedFlag.setAttribute('role', 'combobox');
		selectedFlag.setAttribute('aria-haspopup', 'listbox');
		selectedFlag.setAttribute('aria-expanded', 'false');
		selectedFlag.tabIndex = 0;
		flagContainer.appendChild(selectedFlag);
		this.selectedFlagEl = selectedFlag;

		var flagEl = document.createElement('div');
		flagEl.className = 'iti__flag';
		selectedFlag.appendChild(flagEl);
		this.flagEl = flagEl;

		var arrow = document.createElement('div');
		arrow.className = 'iti__arrow';
		selectedFlag.appendChild(arrow);
		this.arrowEl = arrow;

		/* Dial-code label next to flag */
		var dialLabel = document.createElement('span');
		dialLabel.className = 'iti__selected-dial-code';
		dialLabel.style.marginLeft = '6px';
		dialLabel.style.fontSize = '13px';
		dialLabel.style.color = '#555';
		selectedFlag.appendChild(dialLabel);
		this.dialLabelEl = dialLabel;

		/* Country list dropdown */
		var list = document.createElement('ul');
		list.className = 'iti__country-list iti__hide';
		list.setAttribute('role', 'listbox');
		list.id = 'iti-' + Date.now() + '-list';
		flagContainer.appendChild(list);
		this.listEl = list;

		this._populateList();

		/* Adjust input padding */
		input.style.paddingLeft = '78px';
	};

	/* ------------------------------------------------------------------ */
	/* Populate the country-list dropdown.                                  */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.prototype._populateList = function () {
		var self = this;
		var list = this.listEl;
		var frag = document.createDocumentFragment();

		/* Preferred countries at the top */
		var preferred = this.options.preferredCountries || [];
		var prefCountries = [];
		preferred.forEach(function (iso) {
			var c = self._getCountryByIso2(iso);
			if (c) prefCountries.push(c);
		});

		if (prefCountries.length) {
			prefCountries.forEach(function (c) {
				frag.appendChild(self._buildCountryItem(c));
			});
			var divider = document.createElement('li');
			divider.className = 'iti__divider';
			divider.setAttribute('role', 'separator');
			frag.appendChild(divider);
		}

		/* All countries */
		var sorted = allCountries.slice().sort(function (a, b) {
			return a.name.localeCompare(b.name);
		});
		sorted.forEach(function (c) {
			frag.appendChild(self._buildCountryItem(c));
		});

		list.appendChild(frag);
	};

	IntlTelInputVanilla.prototype._buildCountryItem = function (country) {
		var li = document.createElement('li');
		li.className = 'iti__country';
		li.setAttribute('role', 'option');
		li.setAttribute('data-dial-code', country.dialCode);
		li.setAttribute('data-country-code', country.iso2);

		var flagBox = document.createElement('div');
		flagBox.className = 'iti__flag-box';
		var flag = document.createElement('div');
		flag.className = 'iti__flag iti__' + country.iso2;
		flagBox.appendChild(flag);
		li.appendChild(flagBox);

		var nameSpan = document.createElement('span');
		nameSpan.className = 'iti__country-name';
		nameSpan.textContent = country.name;
		li.appendChild(nameSpan);

		var codeSpan = document.createElement('span');
		codeSpan.className = 'iti__dial-code';
		codeSpan.textContent = '+' + country.dialCode;
		li.appendChild(codeSpan);

		return li;
	};

	/* ------------------------------------------------------------------ */
	/* Event bindings                                                      */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.prototype._bindEvents = function () {
		var self = this;

		/* Toggle dropdown on flag click */
		this.selectedFlagEl.addEventListener('click', function (e) {
			e.stopPropagation();
			self._toggleDropdown();
		});

		/* Keyboard on flag (Enter / Space to open, Escape to close) */
		this.selectedFlagEl.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				self._toggleDropdown();
			} else if (e.key === 'Escape') {
				self._closeDropdown();
			} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (self.listEl.classList.contains('iti__hide')) {
					self._openDropdown();
				}
				self._highlightNext(e.key === 'ArrowDown' ? 1 : -1);
			}
		});

		/* Select country from list */
		this.listEl.addEventListener('click', function (e) {
			var li = e.target.closest('.iti__country');
			if (!li) return;
			var iso = li.getAttribute('data-country-code');
			self._setCountry(iso);
			self._closeDropdown();
			self.telInput.focus();
		});

		/* Keyboard navigation inside dropdown */
		this.listEl.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				self._closeDropdown();
				self.selectedFlagEl.focus();
			} else if (e.key === 'Enter') {
				e.preventDefault();
				var active = self.listEl.querySelector('.iti__highlight');
				if (active) {
					var iso = active.getAttribute('data-country-code');
					self._setCountry(iso);
					self._closeDropdown();
					self.telInput.focus();
				}
			} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				self._highlightNext(e.key === 'ArrowDown' ? 1 : -1);
			} else if (/^[a-zA-Z]$/.test(e.key)) {
				/* Jump to first country starting with typed letter */
				self._jumpToLetter(e.key);
			}
		});

		/* Close dropdown on outside click */
		this._boundDocClick = function () {
			self._closeDropdown();
		};
		document.addEventListener('click', this._boundDocClick);
	};

	/* ------------------------------------------------------------------ */
	/* Dropdown open / close / toggle                                      */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.prototype._toggleDropdown = function () {
		if (this.listEl.classList.contains('iti__hide')) {
			this._openDropdown();
		} else {
			this._closeDropdown();
		}
	};

	IntlTelInputVanilla.prototype._openDropdown = function () {
		this.listEl.classList.remove('iti__hide');
		this.listEl.style.maxHeight = '200px';
		this.arrowEl.classList.add('iti__arrow--up');
		this.selectedFlagEl.setAttribute('aria-expanded', 'true');

		/* Scroll to selected country */
		if (this.selectedCountry) {
			var active = this.listEl.querySelector('[data-country-code="' + this.selectedCountry.iso2 + '"]');
			if (active) {
				active.classList.add('iti__highlight');
				active.scrollIntoView({ block: 'nearest' });
			}
		}
	};

	IntlTelInputVanilla.prototype._closeDropdown = function () {
		this.listEl.classList.add('iti__hide');
		this.arrowEl.classList.remove('iti__arrow--up');
		this.selectedFlagEl.setAttribute('aria-expanded', 'false');

		/* Clear highlights */
		var items = this.listEl.querySelectorAll('.iti__highlight');
		for (var i = 0; i < items.length; i++) {
			items[i].classList.remove('iti__highlight');
		}
	};

	IntlTelInputVanilla.prototype._highlightNext = function (dir) {
		var items = this.listEl.querySelectorAll('.iti__country');
		if (!items.length) return;
		var current = this.listEl.querySelector('.iti__highlight');
		var idx = -1;
		if (current) {
			for (var i = 0; i < items.length; i++) {
				if (items[i] === current) { idx = i; break; }
			}
			current.classList.remove('iti__highlight');
		}
		idx += dir;
		if (idx < 0) idx = items.length - 1;
		if (idx >= items.length) idx = 0;
		items[idx].classList.add('iti__highlight');
		items[idx].scrollIntoView({ block: 'nearest' });
	};

	IntlTelInputVanilla.prototype._jumpToLetter = function (letter) {
		letter = letter.toLowerCase();
		var items = this.listEl.querySelectorAll('.iti__country');
		for (var i = 0; i < items.length; i++) {
			var name = items[i].querySelector('.iti__country-name');
			if (name && name.textContent.trim().toLowerCase().charAt(0) === letter) {
				var prev = this.listEl.querySelector('.iti__highlight');
				if (prev) prev.classList.remove('iti__highlight');
				items[i].classList.add('iti__highlight');
				items[i].scrollIntoView({ block: 'nearest' });
				break;
			}
		}
	};

	/* ------------------------------------------------------------------ */
	/* Set / get selected country                                          */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.prototype._setCountry = function (iso2) {
		var country = this._getCountryByIso2(iso2);
		if (!country) country = this._getCountryByIso2('us');
		if (!country) return;

		this.selectedCountry = country;

		/* Update flag */
		this.flagEl.className = 'iti__flag iti__' + country.iso2;
		this.dialLabelEl.textContent = '+' + country.dialCode;

		/* Update placeholder */
		this.telInput.setAttribute('placeholder', this._getPlaceholder(country));
	};

	IntlTelInputVanilla.prototype._getCountryByIso2 = function (iso2) {
		iso2 = (iso2 || '').toLowerCase();
		for (var i = 0; i < allCountries.length; i++) {
			if (allCountries[i].iso2 === iso2) return allCountries[i];
		}
		return null;
	};

	IntlTelInputVanilla.prototype._getPlaceholder = function (country) {
		/* Build a simple placeholder like "+1 201 555 0123" */
		var range = phoneLengthRanges[country.dialCode];
		if (!range) return '+' + country.dialCode;
		var len = range[0]; /* use minimum length */
		var digits = '';
		for (var i = 0; i < len; i++) digits += (i % 3 === 2 && i < len - 1) ? '0 ' : '0';
		return digits.trim();
	};

	/* ------------------------------------------------------------------ */
	/* Public API — compatible with intlTelInput v17                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Return the full international number in E.164 format.
	 */
	IntlTelInputVanilla.prototype.getNumber = function () {
		var raw = this.telInput.value.replace(/[^\d]/g, '');
		if (!raw) return '';
		if (!this.selectedCountry) return '+' + raw;
		/* If the user typed the full number with country code, return as-is with + */
		if (raw.indexOf(this.selectedCountry.dialCode) === 0) {
			return '+' + raw;
		}
		return '+' + this.selectedCountry.dialCode + raw;
	};

	/**
	 * Return the selected country data object.
	 */
	IntlTelInputVanilla.prototype.getSelectedCountryData = function () {
		return this.selectedCountry || {};
	};

	/**
	 * Basic phone number length validation.
	 */
	IntlTelInputVanilla.prototype.isValidNumber = function () {
		return this.getValidationError() === validationErrors.IS_POSSIBLE;
	};

	/**
	 * Return a validation error code.
	 */
	IntlTelInputVanilla.prototype.getValidationError = function () {
		var raw = this.telInput.value.replace(/[^\d]/g, '');
		if (!raw) return validationErrors.NOT_A_NUMBER;
		if (!this.selectedCountry) return validationErrors.INVALID_COUNTRY_CODE;

		var dialCode = this.selectedCountry.dialCode;

		/* Strip the dial code if the user typed it */
		var national = raw;
		if (raw.indexOf(dialCode) === 0) {
			national = raw.substring(dialCode.length);
		}

		if (!national) return validationErrors.TOO_SHORT;

		var range = phoneLengthRanges[dialCode];
		if (!range) {
			/* Unknown dial code – accept anything 4-15 digits */
			if (national.length < 4) return validationErrors.TOO_SHORT;
			if (national.length > 15) return validationErrors.TOO_LONG;
			return validationErrors.IS_POSSIBLE;
		}

		if (national.length < range[0]) return validationErrors.TOO_SHORT;
		if (national.length > range[1]) return validationErrors.TOO_LONG;
		return validationErrors.IS_POSSIBLE;
	};

	/**
	 * Programmatically set the country.
	 */
	IntlTelInputVanilla.prototype.setCountry = function (iso2) {
		this._setCountry(iso2);
	};

	/**
	 * Programmatically set a phone number.
	 * If the number starts with +, try to detect the country from the dial code.
	 */
	IntlTelInputVanilla.prototype.setNumber = function (number) {
		if (!number) {
			this.telInput.value = '';
			return;
		}
		var cleaned = number.replace(/[^\d+]/g, '');
		if (cleaned.charAt(0) === '+') {
			var digits = cleaned.substring(1);
			/* Try to detect country from dial code (longest match first) */
			for (var len = 3; len >= 1; len--) {
				var prefix = digits.substring(0, len);
				var countries = dialCodeMap[prefix];
				if (countries && countries.length) {
					this._setCountry(countries[0].iso2);
					this.telInput.value = digits.substring(len);
					return;
				}
			}
		}
		this.telInput.value = cleaned.replace(/^\+/, '');
	};

	/**
	 * Clean up: remove DOM wrappers and event listeners.
	 */
	IntlTelInputVanilla.prototype.destroy = function () {
		if (this._boundDocClick) {
			document.removeEventListener('click', this._boundDocClick);
		}
		/* Unwrap */
		if (this.wrap && this.wrap.parentNode) {
			this.wrap.parentNode.insertBefore(this.telInput, this.wrap);
			this.wrap.parentNode.removeChild(this.wrap);
		}
		this.telInput.style.paddingLeft = '';
	};

	/* ------------------------------------------------------------------ */
	/* Geo-IP helper using ipinfo.io (vanilla fetch, no jQuery needed)      */
	/* ------------------------------------------------------------------ */
	IntlTelInputVanilla.geoIpLookup = function (callback) {
		var done = false;
		function finish(code) {
			if (done) return;
			done = true;
			callback(code || '');
		}

		/* Use fetch with a timeout fallback. */
		if (typeof fetch === 'function') {
			fetch('https://ipinfo.io/json?token=', { mode: 'cors' })
				.then(function (r) { return r.json(); })
				.then(function (data) { finish(data && data.country ? data.country : ''); })
				.catch(function () { finish(''); });
		} else {
			/* Fallback: inject a JSONP-style script tag. */
			var cbName = '_itiGeoIp' + Date.now();
			window[cbName] = function (data) {
				finish(data && data.country ? data.country : '');
				delete window[cbName];
			};
			var s = document.createElement('script');
			s.src = 'https://ipinfo.io/?callback=' + cbName;
			s.onerror = function () { finish(''); };
			document.body.appendChild(s);
			/* Safety timeout */
			setTimeout(function () { finish(''); }, 5000);
		}
	};

	/* ------------------------------------------------------------------ */
	/* Factory function                                                    */
	/* ------------------------------------------------------------------ */
	window.intlTelInputVanilla = function (input, options) {
		return new IntlTelInputVanilla(input, options);
	};

	/* Expose geoIpLookup helper */
	window.intlTelInputVanilla.geoIpLookup = IntlTelInputVanilla.geoIpLookup;

	/* Expose error codes */
	window.intlTelInputVanilla.validationErrors = validationErrors;

})();
