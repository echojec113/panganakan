import joblib
import sys
import pandas as pd
import os

# FIX: get absolute path of current file
base_dir = os.path.dirname(os.path.abspath(__file__))
model_path = os.path.join(base_dir, "maternal_risk_model.pkl")

# load trained model
model = joblib.load(model_path)


# feature names (must match training order)
columns = [
    "Age",
    "Gravida",
    "Parity",
    "BP_sys",
    "BP_dia",
    "Weight",
    "Gestational_Age",
    "Hypertension",
    "Diabetes",
    "Prev_CS",
    "Miscarriage",
    "Anemia"
]

# get command line inputs
values = list(map(float, sys.argv[1:]))

# convert to dataframe
data = pd.DataFrame([values], columns=columns)

# predict
prediction = model.predict(data)

if prediction[0] == 1:
    print("HIGH")
else:
    print("LOW")