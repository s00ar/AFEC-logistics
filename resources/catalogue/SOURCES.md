# Procedencia del catálogo inicial

Snapshot leído el 14/10/2026. Son referencias ajustables y observaciones de proyectos concretos; no una promesa de cantidades universales o actuales de todas las variantes del juego.

## Fuentes utilizadas

- Investigación del propietario: `sources/afec-research.csv`, copia byte por byte de `AFEC_catalogo_materiales_colonizacion_verificado_parcial.csv`. 160 registros: 76 materiales de cuatro ejemplos identificados, 33 sin tipo y 51 tipos sin cantidades. El XLSX mencionado por el CSV no fue leído; la evidencia aquí es la investigación aportada.
- [Planificador AFEC](https://docs.google.com/spreadsheets/d/14RxFS9FSc12ybmMuO95vFh8S9An0D4tdgDdEqT6ydEg/edit): metadata y rangos acotados `Catálogo Facilities!A3:K70`, `BA-X c2-14 Research Bio Settlement — Large!A2:C35`, `Col 359 Sector VI-A c1-6!A3:C27` y `Col 359 Sector TN-A c1-20!A3:C34`. El título literal Dodec procede de E1 de la primera hoja. Solo lectura; no se editó Sheets ni se importaron entregas/personas. No equivale a auditar Apps Script ni exportar la planilla completa.
- [Colonization Construction v3, DaftMav](https://docs.google.com/spreadsheets/d/1jsZOzNJSnPIWlU88puOc9gQXvsw-MGp8meoQ6k-Yj-Y/edit): lectura nativa de `Commodities!A1:CX74`, versión indicada en `Changelog!C3:F7` (3.4). Se extraen nombres/códigos internos y celdas numéricas; 66 columnas de recetas con 1322 renglones positivos. Los totales por columna coinciden con las sumas. Los puertos Primary se conservan separados de las construcciones posteriores. La copia ODS encontrada en un repositorio comunitario se inspeccionó para localizar la fuente original, pero el snapshot final usa la planilla original en vivo.

Se conservan en `sources/` solo los rangos necesarios de materiales/metadatos, sin columnas de comandantes/entregas. Cada archivo tiene SHA-256 en `initial-recipes.json`. Las recetas cargadas conservan su clave de fuente y el hash de ese paquete en base de datos y auditoría. El CSV original se marca `-text` en Git para preservar sus saltos de línea; JSON se fija a LF.

## Resultado y discrepancias

71 recetas: 66 comunitarias y 5 observaciones AFEC (cuatro del CSV más el Dodec leído en Sheets). 55 mercancías y 1418 renglones de materiales. Las observaciones AFEC se guardan como variantes separadas, aunque tengan un tipo parecido a una referencia comunitaria; no se mezclan ni se elige una cantidad automáticamente entre fuentes.

- Bio Settlement Large: las 24 cantidades del CSV y Sheets coinciden y suman 8524. La celda TOTALES de AFEC dice 6375 y no se usa. La referencia DaftMav suma 8523; se conservan ambos conjuntos.
- Government Installation: AFEC suma 10211 frente a 10077 de DaftMav (aluminio 2093 frente a 1959). Comms Installation coincide en sus 17 cantidades con la referencia, sumando 6721; aun así se preserva su identidad de observación AFEC.
- Dodec: se conservan el tipo literal y las 20 cantidades del proyecto AFEC. No se infiere su tier ni la condición primaria/posterior a partir de similitudes numéricas.
- Las 33 filas de estructuras sin identificar del CSV y DAVIDENKO EXTRACTION EXCHANGE quedan pendientes de clasificación. No generan recetas con un tipo supuesto. Las filas de tipos sin cantidades permanecen en el informe; no se convierten en objetivos cero ni en recetas vacías.
- La fila Tier de Commodities contiene un error `#REF`; no se importa ese valor ni el cero de la copia ODS. Solo se usan tiers explícitos en nombres o en el catálogo AFEC con correspondencias documentadas en el preparador. El resto queda sin confirmar.
- Los códigos internos de mercancía se conservan como los publica DaftMav, incluidas diferencias respecto al nombre visible (`mutomimager` / Muon Imager, `heliostaticfurnaces` / Microbial Furnaces). Los aliases resuelven espacios/guiones y nombres españoles leídos en AFEC. No se renombra el catálogo existente; coincidencias ambiguas/inactivas detienen la carga.

## Reconstrucción

```text
python tools/build-recipe-catalogue.py --sources resources/catalogue/sources --output resources/catalogue/initial-recipes.json --retrieved-on 2026-10-14
```

Reconstrucción determinista verificada contra el paquete guardado. Python solo prepara datos en desarrollo; producción lee el JSON con PHP y no llama a Google ni requiere Python/Node.
