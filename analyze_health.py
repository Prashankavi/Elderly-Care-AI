import sys
import pandas as pd

# Path to the uploaded file passed as a command line argument
file_path = sys.argv[1]

# Load the data (Assuming CSV format for simplicity)
try:
    df = pd.read_csv(file_path)
except Exception as e:
    print(f"Error reading file: {e}")
    sys.exit(1)

# Logic to check if critical health issues are detected
critical = False
for _, row in df.iterrows():
    # Example: Trigger critical if HR < 50, SpO2 < 90, or BP too low
    if row['HeartRate'] < 50 or row['SpO2'] < 90 or row.get('BloodPressure', 120) < 90:
        critical = True
        break

if critical:
    print("CRITICAL: Low vital signs detected.")
else:
    print("OK: No critical condition.")
