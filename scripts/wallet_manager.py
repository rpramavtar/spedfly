import os
import json
from datetime import datetime

# Attempt to load a MySQL connector library
db_connector = None
try:
    import mysql.connector
    db_connector = 'mysql-connector'
except ImportError:
    try:
        import pymysql
        db_connector = 'pymysql'
    except ImportError:
        pass

def get_db_connection():
    # Read Laravel's .env file dynamically
    config = {}
    # Walk up to the root folder to find .env
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    env_path = os.path.join(base_dir, '.env')
    
    if os.path.exists(env_path):
        with open(env_path, 'r') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                parts = line.split('=', 1)
                if len(parts) == 2:
                    key, val = parts
                    # Clean up quotes
                    val = val.strip().strip('"').strip("'")
                    config[key.strip()] = val
                    
    db_host = config.get('DB_HOST', '127.0.0.1')
    db_port = int(config.get('DB_PORT', '3306'))
    db_user = config.get('DB_USERNAME', 'root')
    db_password = config.get('DB_PASSWORD', '')
    db_name = config.get('DB_DATABASE', 'spedfly')

    if not db_connector:
        raise ImportError("No MySQL connector library found. Please install 'mysql-connector-python' or 'pymysql'.")

    if db_connector == 'mysql-connector':
        return mysql.connector.connect(
            host=db_host,
            port=db_port,
            user=db_user,
            password=db_password,
            database=db_name
        )
    elif db_connector == 'pymysql':
        return pymysql.connect(
            host=db_host,
            port=db_port,
            user=db_user,
            password=db_password,
            database=db_name,
            cursorclass=pymysql.cursors.DictCursor
        )

def get_or_create_wallet(cursor, customer_id, now_str):
    # Try fetching the wallet
    cursor.execute("SELECT * FROM wallets WHERE customer_id = %s", (customer_id,))
    # Fetchone might behave differently depending on the library
    wallet = cursor.fetchone()
    
    # If using mysql-connector, check row mapping
    if wallet and not isinstance(wallet, dict):
        # If cursor was not dictionary cursor, map it manually (mysql-connector default)
        columns = [col[0] for col in cursor.description]
        wallet = dict(zip(columns, wallet))
        
    if not wallet:
        # Create a default wallet
        cursor.execute(
            "INSERT INTO wallets (customer_id, balance, min_threshold, created_at, updated_at) VALUES (%s, %s, %s, %s, %s)",
            (customer_id, 0.00, 100.00, now_str, now_str)
        )
        cursor.execute("SELECT * FROM wallets WHERE customer_id = %s", (customer_id,))
        wallet = cursor.fetchone()
        if wallet and not isinstance(wallet, dict):
            columns = [col[0] for col in cursor.description]
            wallet = dict(zip(columns, wallet))
            
    return wallet

def charge_wallet(customer_id, amount, order_id=None, description=None):
    """
    Charges a customer's prepaid wallet automatically upon a processed order.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    try:
        # Start transaction locking the row
        wallet = get_or_create_wallet(cursor, customer_id, now)
        if not wallet:
            raise ValueError(f"Could not find or create wallet for customer ID: {customer_id}")
            
        wallet_id = wallet['id']
        old_balance = float(wallet['balance'])
        min_threshold = float(wallet['min_threshold'])
        new_balance = old_balance - float(amount)
        
        # 1. Update wallet balance
        cursor.execute(
            "UPDATE wallets SET balance = %s, updated_at = %s WHERE id = %s",
            (new_balance, now, wallet_id)
        )
        
        # 2. Log transaction history
        desc = description if description else f"Charged for Order #{order_id}" if order_id else "Order Charge"
        cursor.execute(
            "INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference_id, balance_after, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
            (wallet_id, -float(amount), 'charge', desc, order_id, new_balance, now, now)
        )
        
        # 3. Handle low balance alert
        if old_balance >= min_threshold and new_balance < min_threshold:
            cursor.execute("SELECT seller_id, name FROM customers WHERE id = %s", (customer_id,))
            customer_row = cursor.fetchone()
            if customer_row:
                if not isinstance(customer_row, dict):
                    columns = [col[0] for col in cursor.description]
                    customer_row = dict(zip(columns, customer_row))
                seller_id = customer_row.get('seller_id')
                customer_name = customer_row.get('name', f'Customer #{customer_id}')
                if seller_id:
                    alert_msg = f"Customer {customer_name}'s wallet balance ({new_balance:.2f}) has dropped below the threshold ({min_threshold:.2f}). Please top up soon."
                    alert_data = json.dumps({"balance": new_balance, "threshold": min_threshold, "customer_id": customer_id, "customer_name": customer_name})
                    cursor.execute(
                        "INSERT INTO app_notifications (user_id, title, message, type, is_read, data, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
                        (seller_id, 'Customer Wallet Low Balance', alert_msg, 'wallet_low_balance', 0, alert_data, now, now)
                    )
            
        conn.commit()
        return {
            "success": True,
            "old_balance": old_balance,
            "new_balance": new_balance,
            "threshold": min_threshold,
            "alert_sent": (old_balance >= min_threshold and new_balance < min_threshold)
        }
    except Exception as e:
        conn.rollback()
        raise e
    finally:
        cursor.close()
        conn.close()

def topup_wallet(customer_id, amount, description=None):
    """
    Manually tops up a customer's prepaid wallet.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    try:
        wallet = get_or_create_wallet(cursor, customer_id, now)
        if not wallet:
            raise ValueError(f"Could not find or create wallet for customer ID: {customer_id}")
            
        wallet_id = wallet['id']
        old_balance = float(wallet['balance'])
        new_balance = old_balance + float(amount)
        
        # 1. Update wallet balance
        cursor.execute(
            "UPDATE wallets SET balance = %s, updated_at = %s WHERE id = %s",
            (new_balance, now, wallet_id)
        )
        
        # 2. Log transaction history
        desc = description if description else "Manual top up"
        cursor.execute(
            "INSERT INTO wallet_transactions (wallet_id, amount, type, description, balance_after, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
            (wallet_id, float(amount), 'topup', desc, new_balance, now, now)
        )
        
        conn.commit()
        return {
            "success": True,
            "old_balance": old_balance,
            "new_balance": new_balance
        }
    except Exception as e:
        conn.rollback()
        raise e
    finally:
        cursor.close()
        conn.close()

# For local script execution testing
if __name__ == '__main__':
    # This is a basic demo check
    print("Prepaid Wallet Manager script loaded.")
    print("Connection helper check: Connector library found =", db_connector)
