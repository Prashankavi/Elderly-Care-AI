import pywhatkit
import sys

def send_alert(phone_number):
    try:
        print(f"Sending alert to {phone_number}...")
        # Optimized: reduce close_time to 1 for faster execution
        pywhatkit.sendwhatmsg_instantly(phone_number, "🚨 Emergency Alert System", tab_close=True, close_time=1)
        print("Message sent!")
    except Exception as e:
        print("Failed:", e)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Phone number required.")
    else:
        number = sys.argv[1]
        send_alert(number)
