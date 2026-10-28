package nuu.im.practice1140216;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ListView;
import android.widget.TextClock;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import java.util.ArrayList;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class Course_Add extends AppCompatActivity {
    //
    private EditText etInput_CourseID;
    private Button btnSearch,btnConfirm, btnReturn;
    private ListView ListViewCourse;
    private MariaDB db;
    private ExecutorService sqlService;
    private ArrayList<String> courseListData = new ArrayList<>();
    private ArrayAdapter<String> courseAdapter;
    private String currentStudentID;
    //
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_course_add);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });
        //
        etInput_CourseID = findViewById(R.id.etInput_CourseID);
        btnSearch = findViewById(R.id.btnSearch);
        btnConfirm = findViewById(R.id.btnConfirm);
        btnReturn = findViewById(R.id.btnReturn);
        ListViewCourse = findViewById(R.id.ListViewCourse);
        //
        sqlService = Executors.newSingleThreadExecutor();
        //
        courseAdapter = new ArrayAdapter<>(this, android.R.layout.simple_list_item_1, courseListData);////
        ListViewCourse.setAdapter(courseAdapter);/////
        //
        SharedPreferences pref = getSharedPreferences("UserSession", MODE_PRIVATE);
        currentStudentID = pref.getString("USER_ID", "");

        Intent intent = getIntent();
        String passedCourseID = intent.getStringExtra("COURSE_ID");

        if (passedCourseID != null && !passedCourseID.isEmpty()) {
            etInput_CourseID.setText(passedCourseID);
        }
        //
        btnSearch.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String inputID = etInput_CourseID.getText().toString().trim();

                sqlService.execute(() -> {
                    db = new MariaDB();
                    ArrayList<String> newList = new ArrayList<>();
                    ArrayList<String> params = new ArrayList<>();
                    String sql;

                    try {
                        if (inputID.isEmpty()) {
                            sql = "SELECT c.CourseID, c.CourseName FROM course c " +
                                    "JOIN score s ON c.CourseID = s.CourseID " +
                                    "WHERE s.StudentID = ?";
                            params.add(currentStudentID);
                        } else {
                            sql = "SELECT * FROM course WHERE CourseID LIKE ?";
                            params.add(inputID);
                        }

                        java.sql.ResultSet rs = db.sqlQuery(sql, params);
                        while (rs.next()) {
                            newList.add(rs.getString("CourseID") + " - " + rs.getString("CourseName"));
                        }

                        runOnUiThread(() -> {
                            courseListData.clear();
                            courseListData.addAll(newList);
                            courseAdapter.notifyDataSetChanged();

                            if (newList.isEmpty()) {
                                Toast.makeText(Course_Add.this, "查無相關課程", Toast.LENGTH_SHORT).show();
                            } else {
                                Toast.makeText(Course_Add.this, "查詢成功", Toast.LENGTH_SHORT).show();
                            }
                        });
                    } catch (Exception e) {
                        e.printStackTrace();
                        runOnUiThread(() -> Toast.makeText(Course_Add.this, "查詢失敗", Toast.LENGTH_SHORT).show());
                    } finally {
                        if (db != null) db.disconnectDB();
                    }
                });
            }
        });

        btnConfirm.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String CourseID = etInput_CourseID.getText().toString().trim();

                if (currentStudentID.isEmpty()) {
                    Toast.makeText(Course_Add.this, "未登入或登入逾時，請重新登入", Toast.LENGTH_SHORT).show();
                    return;
                }
                if (CourseID.isEmpty()) {
                    Toast.makeText(Course_Add.this, "請輸入或選擇課程ID", Toast.LENGTH_SHORT).show();
                    return;
                }

                sqlService.execute(() -> {
                    db = new MariaDB();
                    try {
                        String sql = "INSERT INTO score (StudentID, CourseID) VALUES (?, ?)";
                        ArrayList<String> params = new ArrayList<>();
                        params.add(currentStudentID);
                        params.add(CourseID);
                        db.sqlUpdate(sql, params);

                        runOnUiThread(() -> {
                            Toast.makeText(Course_Add.this, "加選成功", Toast.LENGTH_SHORT).show();
                            finish();
                        });
                    } catch (Exception e) {
                        e.printStackTrace();
                        // 外來鍵或主鍵衝突時會報錯 (例如已經選過這門課了)
                        runOnUiThread(() -> Toast.makeText(Course_Add.this, "加選失敗：可能已選過此課", Toast.LENGTH_SHORT).show());
                    } finally {
                        if (db != null) db.disconnectDB();
                    }
                });
            }
        });

        btnReturn.setOnClickListener(v -> {
            finish();
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
