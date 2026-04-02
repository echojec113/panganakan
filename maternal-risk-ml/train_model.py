import pandas as pd

# Load dataset
df = pd.read_excel("bjjj.xlsx")

# Convert Y/N to 1/0
binary_cols = ["Hypertension", "Diabetes", "Prev_CS", "Miscarriage", "Anemia"]

for col in binary_cols:
    df[col] = df[col].map({"Y":1, "N":0})

# Convert target variable
df["Risk_Level"] = df["Risk_Level"].map({"LOW":0, "HIGH":1})

# Drop Patient column (not useful for ML)
df = df.drop(columns=["Patient"])

print("Processed dataset preview:\n")
print(df.head())

print("\nData types:\n")
print(df.dtypes)



from sklearn.model_selection import train_test_split

# Separate features and target
X = df.drop("Risk_Level", axis=1)
y = df["Risk_Level"]

# Split dataset
X_train, X_test, y_train, y_test = train_test_split(
    X, y,
    test_size=0.2,
    random_state=42
)

print("Training samples:", X_train.shape[0])
print("Testing samples:", X_test.shape[0])

from sklearn.tree import DecisionTreeClassifier

# create model
dt_model = DecisionTreeClassifier(random_state=42)

# train model
dt_model.fit(X_train, y_train)

print("\nDecision Tree model trained successfully!")


# make predictions
y_pred = dt_model.predict(X_test)

print("\nSample predictions:")
print(y_pred[:10])


from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, confusion_matrix

# evaluation metrics
accuracy = accuracy_score(y_test, y_pred)
precision = precision_score(y_test, y_pred)
recall = recall_score(y_test, y_pred)
f1 = f1_score(y_test, y_pred)

print("\nModel Evaluation:")
print("Accuracy:", accuracy)
print("Precision:", precision)
print("Recall:", recall)
print("F1 Score:", f1)

# confusion matrix
print("\nConfusion Matrix:")
print(confusion_matrix(y_test, y_pred))



from sklearn.ensemble import RandomForestClassifier

print("\n==============================")
print("Random Forest Training")
print("==============================")

# create random forest model
rf_model = RandomForestClassifier(
    n_estimators=100,
    random_state=42
)

# train the model
rf_model.fit(X_train, y_train)

print("Random Forest model trained successfully!")

# predictions
rf_pred = rf_model.predict(X_test)

print("\nSample Random Forest predictions:")
print(rf_pred[:10])

# evaluation
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, confusion_matrix

rf_accuracy = accuracy_score(y_test, rf_pred)
rf_precision = precision_score(y_test, rf_pred)
rf_recall = recall_score(y_test, rf_pred)
rf_f1 = f1_score(y_test, rf_pred)

print("\nRandom Forest Evaluation:")
print("Accuracy:", rf_accuracy)
print("Precision:", rf_precision)
print("Recall:", rf_recall)
print("F1 Score:", rf_f1)

print("\nRandom Forest Confusion Matrix:")
print(confusion_matrix(y_test, rf_pred))



import joblib

# save trained random forest model
joblib.dump(rf_model, "maternal_risk_model.pkl")

print("\nRandom Forest model saved as maternal_risk_model.pkl")


import pandas as pd

# Load dataset
df = pd.read_excel("bjjj.xlsx")

# Convert Y/N to 1/0
binary_cols = ["Hypertension", "Diabetes", "Prev_CS", "Miscarriage", "Anemia"]

for col in binary_cols:
    df[col] = df[col].map({"Y":1, "N":0})

# Convert target variable
df["Risk_Level"] = df["Risk_Level"].map({"LOW":0, "HIGH":1})

# Drop Patient column (not useful for ML)
df = df.drop(columns=["Patient"])

print("Processed dataset preview:\n")
print(df.head())

print("\nData types:\n")
print(df.dtypes)



from sklearn.model_selection import train_test_split

# Separate features and target
X = df.drop("Risk_Level", axis=1)
y = df["Risk_Level"]

# Split dataset
X_train, X_test, y_train, y_test = train_test_split(
    X, y,
    test_size=0.2,
    random_state=42
)

print("Training samples:", X_train.shape[0])
print("Testing samples:", X_test.shape[0])

from sklearn.tree import DecisionTreeClassifier

# create model
dt_model = DecisionTreeClassifier(random_state=42)

# train model
dt_model.fit(X_train, y_train)

print("\nDecision Tree model trained successfully!")


# make predictions
y_pred = dt_model.predict(X_test)

print("\nSample predictions:")
print(y_pred[:10])


from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, confusion_matrix

# evaluation metrics
accuracy = accuracy_score(y_test, y_pred)
precision = precision_score(y_test, y_pred)
recall = recall_score(y_test, y_pred)
f1 = f1_score(y_test, y_pred)

print("\nModel Evaluation:")
print("Accuracy:", accuracy)
print("Precision:", precision)
print("Recall:", recall)
print("F1 Score:", f1)

# confusion matrix
print("\nConfusion Matrix:")
print(confusion_matrix(y_test, y_pred))



from sklearn.ensemble import RandomForestClassifier

print("\n==============================")
print("Random Forest Training")
print("==============================")

# create random forest model
rf_model = RandomForestClassifier(
    n_estimators=100,
    random_state=42
)

# train the model
rf_model.fit(X_train, y_train)

print("Random Forest model trained successfully!")

# predictions
rf_pred = rf_model.predict(X_test)

print("\nSample Random Forest predictions:")
print(rf_pred[:10])

# evaluation
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, confusion_matrix

rf_accuracy = accuracy_score(y_test, rf_pred)
rf_precision = precision_score(y_test, rf_pred)
rf_recall = recall_score(y_test, rf_pred)
rf_f1 = f1_score(y_test, rf_pred)

print("\nRandom Forest Evaluation:")
print("Accuracy:", rf_accuracy)
print("Precision:", rf_precision)
print("Recall:", rf_recall)
print("F1 Score:", rf_f1)

print("\nRandom Forest Confusion Matrix:")
print(confusion_matrix(y_test, rf_pred))



import joblib

# save trained random forest model
joblib.dump(rf_model, "maternal_risk_model.pkl")

print("\nRandom Forest model saved as maternal_risk_model.pkl")


print("\n==============================")
print("MULTI-ALGORITHM COMPARISON")
print("==============================")

from sklearn.linear_model import LogisticRegression
from sklearn.neighbors import KNeighborsClassifier
from sklearn.naive_bayes import GaussianNB

models = {
    "Decision Tree": dt_model,
    "Random Forest": rf_model,
    "Logistic Regression": LogisticRegression(max_iter=1000),
    "KNN": KNeighborsClassifier(),
    "Naive Bayes": GaussianNB()
}

results = []

for name, model in models.items():

    # Train (skip retrain for already trained models if you want, but safe to retrain)
    model.fit(X_train, y_train)

    # Predict
    y_pred = model.predict(X_test)

    # Metrics
    accuracy = accuracy_score(y_test, y_pred)
    precision = precision_score(y_test, y_pred, zero_division=0)
    recall = recall_score(y_test, y_pred, zero_division=0)
    f1 = f1_score(y_test, y_pred, zero_division=0)

    results.append([name, accuracy, precision, recall, f1])


# DISPLAY RESULTS
print("\n=== FINAL MODEL COMPARISON ===\n")
print(f"{'Model':<20} {'Accuracy':<10} {'Precision':<10} {'Recall':<10} {'F1':<10}")

for r in results:
    print(f"{r[0]:<20} {r[1]:<10.3f} {r[2]:<10.3f} {r[3]:<10.3f} {r[4]:<10.3f}")