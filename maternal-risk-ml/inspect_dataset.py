import pandas as pd

# load excel dataset
df = pd.read_excel("bjjj.xlsx")

print("First 5 rows:")
print(df.head())

print("\nDataset Info:")
print(df.info())

print("\nMissing values:")
print(df.isnull().sum())