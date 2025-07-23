# Code Coverage - GitHub Integration

Tento projekt má nakonfigurovaný komplexný systém pre sledovanie code coverage s GitHub Actions a rozličnými nástrojmi pre vizualizáciu.

## 🎯 Čo získate

### Automatické coverage reporty
- **Percentuálne pokrytie** v každom pull requeste
- **Detailné HTML reporty** s vizualizáciou pokrytých/nepokrytých riadkov
- **Codecov integrácia** pre sledovanie trendov coverage
- **GitHub Pages deployment** s interaktívnymi reportmi

### Kde nájdete coverage informácie

#### 1. Pull Request komentáre
Pri každom PR automaticky dostanete komentár s:
- Aktuálne percentuálne pokrytie
- Zmeny oproti predchádzajúcej verzii
- Zoznam súborov s nízkym pokrytím
- Odkazy na detailné reporty

#### 2. GitHub Actions výstup
V "Actions" tabe uvidíte:
- Coverage percentage v názve jobu
- Textový output s pokrytím po súboroch
- Chybové hlášky ak coverage klesne pod minimum (70%)

#### 3. Codecov dashboard
Na https://codecov.io/{username}/{repo}:
- Grafy trendov coverage v čase
- Pokrytie jednotlivých súborov a funkcií
- Porovnanie medzi branchmi
- Coverage sunburst vizualizácie

#### 4. GitHub Pages reporty
Na https://{username}.github.io/{repo}/coverage/:
- Interaktívne HTML reporty
- Klik na súbor = detail pokrytia po riadkoch
- Farebné označenie pokrytých/nepokrytých riadkov
- Aktualizuje sa automaticky pri push do main/master

## 🚀 Lokálne testovanie coverage

### Základný coverage report
```bash
vendor/bin/phpunit --coverage-text
```

### Generovanie HTML reportov
```bash
# Vytvorí interaktívny HTML report v build/coverage-html/
vendor/bin/phpunit --coverage-html build/coverage-html

# Otvorenie v prehliadači
open build/coverage-html/index.html  # macOS
xdg-open build/coverage-html/index.html  # Linux
```

### XML coverage pre nástroje
```bash
# Clover format pre Codecov, PHPStorm, atď.
vendor/bin/phpunit --coverage-clover build/logs/clover.xml
```

### Všetko naraz
```bash
vendor/bin/phpunit \
  --coverage-text \
  --coverage-html build/coverage-html \
  --coverage-clover build/logs/clover.xml
```

## 📊 Nastavenie minimálneho coverage

Aktuálne minimum je nastavené na **70%**. Pre zmenu editujte:

1. **GitHub Actions** (`.github/workflows/phpunit.yml`):
   ```yaml
   percentage: "70"
   minimum_coverage: 70
   ```

2. **Lokálne testovanie**:
   ```bash
   vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml
   # Skontrolovať v coverage.xml alebo textovom výstupe
   ```

## 🔧 Riešenie problémov

### Coverage sa negeneruje
Skontrolujte, či máte nainštalované Xdebug:
```bash
php -m | grep xdebug
```

### Nízke coverage hodnoty
1. Pozrite si HTML report pre detaily
2. Skontrolujte `@covers` annotations v testoch
3. Uistite sa, že testy skutočně volajú váš kód

### GitHub Pages nefungujú
1. Povoľte GitHub Pages v Settings > Pages
2. Nastavte source na "GitHub Actions"
3. Skontrolujte, či máte správne permissions

## 📈 Coverage metriky

### Čo sa meria
- **Line Coverage**: Aké % riadkov kódu je vykonané testmi
- **Function Coverage**: Aké % funkcií/metód je testovaných
- **Branch Coverage**: Aké % podmienok (if/else) je testovaných

### Ideálne hodnoty
- **90%+**: Výborné pokrytie
- **70-89%**: Dobré pokrytie
- **50-69%**: Potrebuje zlepšenie
- **<50%**: Kriticky nízke

## 🎨 Interpretácia farebného kódovania

V HTML reportoch:
- 🟢 **Zelená**: Riadok je pokrytý testmi
- 🔴 **Červená**: Riadok nie je pokrytý
- 🟡 **Žltá**: Riadok je čiastočne pokrytý (napr. iba jedna vetva if/else)
- ⚪ **Biela**: Nepočíta sa do coverage (komentáre, prázdne riadky)