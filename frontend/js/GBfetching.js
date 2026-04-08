const sheetUrl =
  "https://docs.google.com/spreadsheets/d/e/2PACX-1vS10OiVKg-emsE3HeMukI18ioBJ9bPIb90UtDNkVyk-kAsTGn_xbX5ZoAnYf4hrP1vWa-brAuVGGu1g/pub?output=csv";

fetch(sheetUrl)
  .then((response) => response.text())
  .then((csvText) => {
    const rows = csvText.split("\n").map((row) => row.split(","));

    const headers = rows[0].map((h) => h.trim().replace(/^\uFEFF/, ""));

    console.log("Detected headers:", headers);

    const data = rows.slice(1).map((row) => {
      let obj = {};
      headers.forEach((header, i) => {
        obj[header] = row[i]?.trim() || "";
      });
      return obj;
    });

    data.forEach((student) => {
      const panel = student[headers[3]];

      if (panel.includes("~")) {
        const parts = panel.split("~");
        console.log(parts);
      } else {
        console.log(`Panel without ${panel}`);
      }
    });
  })
  .catch((err) => console.error(err));
