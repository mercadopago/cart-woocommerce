#!bin/bash

cp dist/mp-plugins-components.js assets/js/checkouts/mp-plugins-components.js
cp dist/mp-plugins-components.js assets/js/checkouts/mp-plugins-components.min.js

cp dist/mp-plugins-styles.css assets/css/checkouts/mp-plugins-components.css
cp dist/mp-plugins-styles.css assets/css/checkouts/mp-plugins-components.min.css

rm -rf dist
