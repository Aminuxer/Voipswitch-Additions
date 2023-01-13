-- Show free DIDs WITH Prices
SELECT ld.did, lac.name,
    pc.country_code, pc.country_name, pc.country_phonecode, pc.setup_fee, pc.monthly_fee
FROM portal_localdids  ld
    LEFT JOIN portal_clientdids cd ON cd.phone_number = ld.did
    LEFT JOIN portal_localareacodes lac ON lac.id = ld.areacode_id
    LEFT JOIN portal_countries pc ON lac.country_id = pc.id
WHERE ld.assigned = 0 AND cd.id IS NULL
