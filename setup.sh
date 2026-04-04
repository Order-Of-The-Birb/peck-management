#!/bin/bash
cd "$(dirname "$0")" || exit
python3.14 -m venv .venv
source .venv/bin/activate
python3.14 -m pip install --upgrade pip -q
pip install -r requirements.txt -q
deactivate
echo "Done"
read -rp "Press any key to continue..." -n1
echo
