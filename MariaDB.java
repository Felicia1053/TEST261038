package nuu.im.practice1140216;

import android.util.Log;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;

class MariaDB
{
    private final String serverIP="10.0.2.2";
    //
    private PreparedStatement ps = null;
    private Connection con = null;

    //建構子：建立連線物件
    MariaDB()
    {
        try
        {
            //連接資料庫
            Class.forName("org.mariadb.jdbc.Driver");
            Log.v("DB", "加載驅動成功");
            //
            con = DriverManager.getConnection("jdbc:mariadb://"+serverIP+"/student?useUnicode=true&characterEncoding=UTF-8&useSSL=false", "student", "dora8520");
            Log.v("DB", "遠端連接成功");
        }
        catch(ClassNotFoundException e)
        {
            e.printStackTrace();
            Log.e("DB", "加載驅動失敗");
        }
        catch(SQLException x)
        {
            Log.e("DB", "遠端連接失敗");
            Log.e("DB", x.toString());
        }
    }

    //關閉資料庫
    void disconnectDB()
    {
        try
        {
            con.close();
            Log.e("DB", "Database successfully disconnected...");
        }
        catch(SQLException e)
        {
            Log.e("DB", "Close Exception : " + e);
        }
    }
//sqlQuery不需要將資料寫入資料庫
    ResultSet sqlQuery(String psSqlStatement, ArrayList<String> parameters)
    {
        if (con == null) {
            Log.e("DB", "嚴重錯誤：資料庫未連線，已攔截閃退");
            return null;
        }

        ResultSet rs = null;
        int i=1;
        //
        try
        {
            ps = con.prepareStatement(psSqlStatement);
            //
            for(String str: parameters)
            {
                ps.setString(i++, str);
            }
            rs = ps.executeQuery();
        }
        catch(SQLException e)
        {
            Log.e("DB", "SQLException : " + e);
        }
        //
        return rs;
    }
//需要將資料寫入資料庫
    void sqlUpdate(String psSqlStatement, ArrayList<String> parameters)
    {
        if (con == null) {
            Log.e("DB", "嚴重錯誤：資料庫未連線，已攔截閃退");
            return;
        }

        int i=1;
        //
        try
        {
            ps = con.prepareStatement(psSqlStatement);
            //
            for(String str: parameters)
            {
                ps.setString(i++, str);
            }
            ps.executeUpdate();
        }
        catch(SQLException e)
        {
            Log.e("DB", "SQLException : " + e);
        }
    }

    public int rsSize(ResultSet rs)
    {
        if (rs == null) return 0;

        int size=0;
        //
        try
        {
            rs.last();
            size = rs.getRow();
            rs.beforeFirst();
        }
        catch (SQLException e)
        {
            // TODO Auto-generated catch block
            e.printStackTrace();
        }
        //
        return size;
    }

}