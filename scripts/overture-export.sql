-- Overture Maps Places → CSV (kendi bilgisayarınızda DuckDB ile çalıştırın)
--   duckdb < scripts/overture-export.sql
--
-- Önce aşağıdaki 3 yeri düzenleyin:
--   1) RELEASE : https://docs.overturemaps.org/getting-data/ sayfasındaki güncel sürüm (ör. 2026-xx-xx.0)
--   2) bbox    : ilgilendiğiniz il/ilçe sınırları (xmin=boylam min, xmax=boylam max, ymin=enlem min, ymax=enlem max)
--   3) kategori listesi : config/leadgen.php → file_import.category_map anahtarları
--
-- UYARI: Alan adları Overture şemasına göre yazıldı; sürümünüzde farklıysa
-- (ör. categories.primary yerine basic_category, operating_status yoksa) sorguyu uyarlayın:
--   https://docs.overturemaps.org/schema/reference/places/place
INSTALL httpfs;
LOAD httpfs;
SET s3_region = 'us-west-2';

COPY (
    SELECT
        id,
        names.primary                                   AS name,
        concat_ws(', ', addresses[1].freeform, addresses[1].locality, addresses[1].region) AS address,
        phones[1]                                       AS phone,
        websites[1]                                     AS website,
        emails[1]                                       AS email,
        socials[1]                                      AS social,
        bbox.ymin                                       AS lat,   -- nokta geometrilerde bbox = konum (spatial eklentisi gerekmez)
        bbox.xmin                                       AS lng,
        categories.primary                              AS category,
        confidence,
        operating_status                                AS status      -- sürümünüzde yoksa bu satırı silin
    FROM read_parquet('s3://overturemaps-us-west-2/release/RELEASE/theme=places/type=place/*',
                      filename = true, hive_partitioning = 1)
    WHERE bbox.xmin BETWEEN 28.60 AND 29.50      -- örnek: İstanbul
      AND bbox.ymin BETWEEN 40.80 AND 41.30
      AND categories.primary IN ('pet_store', 'veterinarian', 'restaurant', 'cafe', 'bakery',
                                 'hair_salon', 'barber', 'beauty_salon', 'automotive_repair',
                                 'car_wash', 'tire_shop', 'florist')
      AND confidence >= 0.6
      AND (websites IS NULL OR len(websites) = 0)  -- web sitesi kaydı olmayanlar (yine de elle doğrulanır)
) TO 'places.csv' (HEADER, DELIMITER ',');
