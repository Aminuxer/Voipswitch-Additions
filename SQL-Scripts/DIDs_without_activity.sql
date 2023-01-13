-- DIDs, Logins and discharges WITH SO FAR LAST ACTIVITY
SELECT ic.Name, ic.LastName, ic.Address, ic.EMail, ic.Phone, ic.MobilePhone, ic.Creation_Date,
    ld.did,  cs.account_state, lac.name, cd.client_id, cd.client_type, cs.login,
    (SELECT MAX(data) FROM payments p WHERE p.id_client = cd.client_id AND p.client_type = cd.client_type AND p.type = 3) as last_discharge,
    (SELECT MAX(data) FROM payments p WHERE p.id_client = cd.client_id AND p.client_type = cd.client_type AND p.type = 1) as last_payment,
    (SELECT MAX(call_start) FROM calls c WHERE c.id_client = cd.client_id AND c.client_type = cd.client_type) as last_call,
    pc.country_code, pc.country_name, pc.country_phonecode, pc.setup_fee, pc.monthly_fee
FROM portal_localdids  ld
    LEFT JOIN portal_clientdids cd ON cd.phone_number = ld.did
    LEFT JOIN portal_localareacodes lac ON lac.id = ld.areacode_id
    LEFT JOIN portal_countries pc ON lac.country_id = pc.id
    LEFT JOIN clientsshared cs ON cs.id_client = cd.client_id
    LEFT JOIN invoiceclients ic ON ic.IDClient = cd.client_id and ic.Type = cd.client_type
WHERE ld.assigned != 0
  -- AND country_name = 'Russia'
 -- AND cs.login NOT IN ( 0123, 012345 )
HAVING last_discharge < '2015-01-01' and last_payment < '2015-01-01'   AND last_call < '2015-01-01'
ORDER BY last_payment DESC
