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

public class Course_Search extends AppCompatActivity {
    //
    private EditText etInput_CourseName;
    private Button btnSearch, btnCoureseAdd, btnTo_User_Info;
    private TextView tvCount;
    private ListView ListViewCourse;
    private MariaDB db;
    private ExecutorService sqlService;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_course_search);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etInput_CourseName = findViewById(R.id.etInput_CourseName);
        btnSearch = findViewById(R.id.btnSearch);
        btnCoureseAdd = findViewById(R.id.btnCoureseAdd);
        tvCount = findViewById(R.id.tvCount);
        ListViewCourse = findViewById(R.id.ListViewCourse);
        btnTo_User_Info = findViewById(R.id.btnTo_User_Info);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        btnTo_User_Info.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Intent intent = new Intent(Course_Search.this, User_Info.class);
                startActivity(intent);
            }
        });
        //
        btnSearch.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String CourseName = etInput_CourseName.getText().toString().trim();

                sqlService.execute(() -> {
                    db = new MariaDB();
                    String sql;
                    ArrayList<String> params = new ArrayList<>();
                    //
                    if (CourseName.isEmpty()) {
                        sql = "SELECT * FROM course";
                    } else {
                        sql = "SELECT * FROM course WHERE CourseName LIKE ?";
                        params.add("%" + CourseName + "%");
                    }

                    try {
                        ResultSet rs = db.sqlQuery(sql, params);
                        ArrayList<String> courseList = new ArrayList<>();

                        while (rs.next()) {
                            String cID = rs.getString("CourseID");
                            String cName = rs.getString("CourseName");
                            courseList.add(cID + " - " + cName);
                        }

                        runOnUiThread(() -> {
                            ArrayAdapter<String> adapter = new ArrayAdapter<>(
                                    Course_Search.this,android.R.layout.simple_list_item_1,courseList);
                            ListViewCourse.setAdapter(adapter);

                            int count = courseList.size();
                            tvCount.setText(String.valueOf(count));


                            //看 courseList 裡面有沒有資料
                            if (courseList.isEmpty()) {
                                Toast.makeText(Course_Search.this, "查無此課程", Toast.LENGTH_SHORT).show();
                            } else if (CourseName.isEmpty()) {
                                Toast.makeText(Course_Search.this, "顯示所有課程", Toast.LENGTH_SHORT).show();
                            } else {
                                Toast.makeText(Course_Search.this, "查詢成功", Toast.LENGTH_SHORT).show();
                            }
                        });

                    } catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> Toast.makeText(Course_Search.this, "資料庫查詢失敗", Toast.LENGTH_SHORT).show());
                    } finally {
                        if (db != null) db.disconnectDB();
                    }
                });
            }
        });

        ListViewCourse.setOnItemClickListener(new android.widget.AdapterView.OnItemClickListener() {
            @Override
            public void onItemClick(android.widget.AdapterView<?> parent, View view, int position, long id) {
                if (parent.getItemAtPosition(position) == null)
                    return;
                String selectedItem = (String) parent.getItemAtPosition(position);
                String courseID = selectedItem.split(" - ")[0]; // 切出課程代碼

                Intent intent = new Intent(Course_Search.this, Course_Add.class);

                intent.putExtra("COURSE_ID", courseID);
                Toast.makeText(Course_Search.this, "前往加選", Toast.LENGTH_SHORT).show();
                startActivity(intent);
            }
        });

        btnCoureseAdd.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Intent intent = new Intent(Course_Search.this, Course_Add.class);
                startActivity(intent);
                Toast.makeText(Course_Search.this, "前往加選", Toast.LENGTH_SHORT).show();
            }
        });
    }
    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (sqlService != null) {
            sqlService.shutdown();
        }
    }
}