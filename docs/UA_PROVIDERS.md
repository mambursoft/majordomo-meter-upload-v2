# Ukrainian providers: what is actually available

Reviewed in November 2026. Public documented APIs are **not available** for the mainstream Ukrainian utility providers. They use web forms, personal cabinets, SMS codes or captcha. Therefore the module does not pretend to have an API: it stores the official page URL and can either open it with prefilled parameters (`manual` mode) or POST JSON to your own endpoint (`api` mode).

| Provider | Service page | Identity | Submission | Notes |
|---|---|---|---|---|
| Ясно | https://yasno.ua/indicators | account or address | web form, no captcha for basic submission | history requires https://yasno.ua/my/; business cabinet https://mybiz.kyiv.yasno.com.ua/ |
| КиївВодоканал | https://my.vodokanal.kiev.ua/ and https://kvkmeters.ssmt.com.ua/ | personal account | cabinet / quick form | cold and hot water meters separately |
| Київтеплоенерго | https://kte.kmda.gov.ua/my-cabinet/ | 15-digit account + house + apartment | web form with captcha | hot water and heat |
| ДТЕК Київські електромережі | https://www.dtek-kem.com.ua/ua/meter | personal account | web form, no registration | last 2 days of month / first 3 days of next |
| Київгаз | https://kyivgaz.ua/ | account | operator site | recommended 1–3 of month |
| Львівгаз | https://lvivgaz.ua/ | account | operator site | recommended 1–3 of month |

## Practical recommendation

Use `manual` mode for these providers: the module returns a ready URL with account and readings in the query string, and the user confirms the form in a browser. Use `api` mode only when you have your own gateway or a provider endpoint with a token.
