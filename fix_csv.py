import csv

input_file = 'produkty_loreal.csv'
output_file = 'produkty_loreal_opravene.csv'

with open(input_file, mode='r', encoding='utf-8') as infile, \
     open(output_file, mode='w', encoding='utf-8', newline='') as outfile:
    
    reader = csv.reader(infile, delimiter=';')
    writer = csv.writer(outfile, delimiter=';')
    
    header = next(reader)
    # Přidáme sloupec s cenou
    if "Cena" not in header:
        header.append("Cena")
    writer.writerow(header)
    
    for row in reader:
        # Změníme 'Salon' na 'Retail (Náplň)' pro prodej balení
        if len(row) > 4 and row[4] == 'Salon':
            row[4] = 'Retail (Náplň)'
        
        if len(row) == 5:
            row.append("0") # Výchozí cena 0
            
        writer.writerow(row)

print("Hotovo! CSV bylo upraveno a ulozeno jako produkty_loreal_opravene.csv")
