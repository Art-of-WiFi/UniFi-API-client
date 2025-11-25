# Things todo

## Next commit
- API client class v2.0.9
- merged PR #282 to handle deprecated `curl_close()` function in PHP >8.5, contributed by @dream-rhythm


## Routes to add
- /v2/api/site/mdfwaanp/models
  -  output example:
  ```json
   {
    "model_list": [
        {
            "default_image_id": "73f9d3a4696f0b054c2e3b54a649222e",
            "id": "b5a1a302-35fe-4605-92ef-6a4f66222097",
            "model_abbreviation": "USW Pro 8 PoE",
            "model_fullName": "Switch Pro 8 PoE",
            "model_name": "USLP8P",
            "model_sysid": "ed5a",
            "nopadding_image_id": "567137edae9f99ecf99958560aad1b0e",
            "sku": "USW-Pro-8-PoE",
            "topology_image_id": "597b0990c410ded0bcc9069d6f308312"
        },
        {
            "default_image_id": "4fac659e9e888a9298a9f24ce02373b2",
            "id": "181ce560-876b-4619-bee6-5f4e20fb2f93",
            "model_abbreviation": "AC Lite",
            "model_fullName": "Access Point AC Lite",
            "model_name": "U7LT",
            "model_sysid": "e517",
            "nopadding_image_id": "1f7055cef4ac72105793b78116c49be4",
            "sku": "UAP-AC-LITE",
            "topology_image_id": "38a7861f0e5a0dd684f182bdd2384ed2"
        },
        {
            "default_image_id": "e37ba48acdfe7e94f62d49a757e37db4",
            "id": "f84319ea-3df6-429e-a0b5-36587271d094",
            "model_abbreviation": "FlexHD",
            "model_fullName": "Access Point FlexHD",
            "model_name": "UFLHD",
            "model_sysid": "ec26",
            "nopadding_image_id": "e61c75aa3151978ce8ef07735d15a154",
            "sku": "UAP-FlexHD",
            "topology_image_id": "eb04daf3e215ef98650a75eda5ca8cfa"
        },
        {
            "default_image_id": "7ab4da709aff50bae187555a6d084f90",
            "id": "8d084b12-98d6-4b10-9168-cf319e170cef",
            "model_abbreviation": "USW Lite 16 PoE",
            "model_fullName": "Switch Lite 16 PoE",
            "model_name": "USL16LP",
            "model_sysid": "ed26",
            "nopadding_image_id": "c1e76f81b46a31d8da6ba7de56cb33cf",
            "sku": "USW-Lite-16-PoE",
            "topology_image_id": "51983b94e9b75075f12c836f7be994ef"
        },
        {
            "default_image_id": "88b840fbb548b263bac30e4d335d5f5a",
            "id": "dac2c752-2b31-406f-96ed-36859261f293",
            "model_abbreviation": "UXG Max",
            "model_fullName": "Gateway Max",
            "model_name": "UXGB",
            "model_sysid": "a690",
            "nopadding_image_id": "5a3ce86efe3466e8166fdce51245c3bf",
            "sku": "UXG-Max",
            "topology_image_id": "7023b1f7e2d5b7835ba752b2d73786c7"
        },
        {
            "default_image_id": "4fac659e9e888a9298a9f24ce02373b2",
            "id": "fa66c9b2-ba69-4f34-b346-051753cda2e9",
            "model_abbreviation": "U6 LR+",
            "model_fullName": "Access Point U6 Long-Range+",
            "model_name": "UALRPL6",
            "model_sysid": "a643",
            "nopadding_image_id": "1f7055cef4ac72105793b78116c49be4",
            "sku": "U6-PLUS-LR",
            "topology_image_id": "38a7861f0e5a0dd684f182bdd2384ed2"
        },
        {
            "default_image_id": "1ed05326d26c963560c53bcd6fb1b2ae",
            "id": "b6a7f692-39e2-4942-989e-874e5166ef3b",
            "model_abbreviation": "USW Lite 8 PoE",
            "model_fullName": "Switch Lite 8 PoE",
            "model_name": "USL8LP",
            "model_sysid": "ed2a",
            "nopadding_image_id": "e949b1092dc5ef48507eeadf2cbf54f9",
            "sku": "USW-Lite-8-PoE",
            "topology_image_id": "46351a4e28941c0f17caf96515e94921"
        },
        {
            "default_image_id": "d63e8b9562026c12b4b50223cfc93170",
            "id": "95b82866-f941-4cb0-aa79-58392dbcb7f6",
            "model_abbreviation": "USW Pro 24 PoE",
            "model_fullName": "Switch Pro 24 PoE",
            "model_name": "US24PRO",
            "model_sysid": "eb36",
            "nopadding_image_id": "b1cd82277458c19e297cd2d9155b32c7",
            "sku": "USW-Pro-24-PoE",
            "topology_image_id": "61340cc158b3b9a1e6208f254749b5e7"
        },
        {
            "default_image_id": "fdde25abe697d20acefee603964b9e1f",
            "id": "5931f835-5029-42da-8676-c4e57003b2cd",
            "model_abbreviation": "USW Enterprise 8 PoE",
            "model_fullName": "Switch Enterprise 8 PoE",
            "model_name": "US68P",
            "model_sysid": "ed41",
            "nopadding_image_id": "d523e7d05829b2af8f543963f38938b2",
            "sku": "USW-Enterprise-8-PoE",
            "topology_image_id": "afe8af0c80987dfbe67c468fca6dc107"
        },
        {
            "default_image_id": "f3707f0e330a3a3d01a90e8f1453123f",
            "id": "6f5cefaf-6810-4525-aafd-ea704c1f50b9",
            "model_abbreviation": "U7 Pro XG",
            "model_fullName": "Access Point U7 Pro XG",
            "model_name": "UAPA6AE",
            "model_sysid": "a6ae",
            "nopadding_image_id": "8e935c2f582b72b1663bff60d2fe9b6d",
            "sku": "U7-Pro-XG-B",
            "topology_image_id": "2a0688bcbc8ac4d9dc70518dcccb31a9"
        },
        {
            "default_image_id": "4fac659e9e888a9298a9f24ce02373b2",
            "id": "bc0e1444-3b03-4b10-9997-4f200cdf3709",
            "model_abbreviation": "AC HD",
            "model_fullName": "Access Point AC HD",
            "model_name": "U7HD",
            "model_sysid": "e530",
            "nopadding_image_id": "1f7055cef4ac72105793b78116c49be4",
            "sku": "UAP-AC-HD",
            "topology_image_id": "38a7861f0e5a0dd684f182bdd2384ed2"
        },
        {
            "default_image_id": "b2c61e16e3576a6dd64ebda28fa0e946",
            "id": "1725ba05-79e0-43d1-9551-be834f7fe536",
            "model_abbreviation": "USW Flex",
            "model_fullName": "Switch Flex",
            "model_name": "USF5P",
            "model_sysid": "ed10",
            "nopadding_image_id": "28f4d753fcee6d44554dc51004cb2dc3",
            "sku": "USW-Flex",
            "topology_image_id": "4377d80591a11c08dea39280b18d40d9"
        }
    ]
}
  ```