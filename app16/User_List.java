package nuu.im.practice1140216;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class User_List extends AppCompatActivity {
    //
    private EditText etInput;
    private TextView tvCount;
    private Button btnReturn, btnAdd, btnSearch;
    private ListView UserList;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_user_list);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etInput = findViewById(R.id.etInput);
        btnReturn = findViewById(R.id.btnReturn);
        btnAdd = findViewById(R.id.btnAdd);
        UserList = findViewById(R.id.UserList);
        btnSearch = findViewById(R.id.btnSearch);
        tvCount = findViewById(R.id.tvCount);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnReturn.setOnClickListener(v -> {
            finish();
        });
        //
        btnSearch.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String UserName = etInput.getText().toString().trim();

                sqlService.execute(() -> {
                    db = new MariaDB();
                    String sql;
                    ArrayList<String> params = new ArrayList<>();
                    //
                    if (UserName.isEmpty()) {
                        sql = "SELECT * FROM user";
                    } else {
                        sql = "SELECT * FROM user WHERE UserName LIKE ?";
                        params.add("%" + UserName + "%");
                    }

                    try {
                        ResultSet rs = db.sqlQuery(sql, params);
                        ArrayList<String> userListData = new ArrayList<>();

                        while (rs.next()) {
                            String uID = rs.getString("UserID");
                            String uClass = rs.getString("Class");
                            String uName = rs.getString("UserName");
                            userListData.add(uID + " - " + uClass + " - " + uName);
                        }

                        runOnUiThread(() -> {
                            ArrayAdapter<String> adapter = new ArrayAdapter<>(
                                    User_List.this, android.R.layout.simple_list_item_1, userListData);
                            UserList.setAdapter(adapter);

                            int count = userListData.size();
                            tvCount.setText(String.valueOf(count));

                            if (userListData.isEmpty()) {
                                Toast.makeText(User_List.this, "查無此人", Toast.LENGTH_SHORT).show();
                            } else if (UserName.isEmpty()) {
                                Toast.makeText(User_List.this, "顯示所有使用者", Toast.LENGTH_SHORT).show();
                            } else {
                                Toast.makeText(User_List.this, "查詢成功", Toast.LENGTH_SHORT).show();
                            }
                        });
                    } catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> Toast.makeText(User_List.this, "資料庫查詢失敗", Toast.LENGTH_SHORT).show());
                    } finally {
                        if (db != null) db.disconnectDB();
                    }
                });
            }
        });
        //
        btnAdd.setOnClickListener(v -> {
            Intent intent = new Intent(User_List.this, Add_User.class);
            Toast.makeText(User_List.this, "前往新增使用者", Toast.LENGTH_SHORT).show();
            startActivity(intent);
        });
        //
        UserList.setOnItemClickListener(new android.widget.AdapterView.OnItemClickListener() {
            @Override
            public void onItemClick(android.widget.AdapterView<?> parent, View view, int position, long id) {
                if (parent.getItemAtPosition(position) == null)
                    return;
                String selectedItem = (String) parent.getItemAtPosition(position);
                String[] parts = selectedItem.split(" - ");
                if (parts.length >= 1) {
                    String userId = parts[0].trim();

                    Intent intent = new Intent(User_List.this, Add_User.class);
                    intent.putExtra("UserID", userId);

                    Toast.makeText(User_List.this, "編輯使用者", Toast.LENGTH_SHORT).show();
                    startActivity(intent);
                }
            }
        });
    }
    //
    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (sqlService != null) {
            sqlService.shutdown();
        }
    }
}
