import streamlit as st
import requests
import xml.etree.ElementTree as ET
import nltk
from nltk.tokenize import word_tokenize
from nltk.corpus import stopwords
from nltk.stem import WordNetLemmatizer
from collections import Counter
import pandas as pd

# -----------------
# 1. 網頁基本設定
# -----------------
st.set_page_config(page_title="arXiv 論文趨勢分析", page_icon="📊", layout="wide")

# -----------------
# 2. NLP 資源下載與快取
# -----------------
@st.cache_resource
def download_nltk_data():
    nltk.download('punkt_tab', quiet=True)
    nltk.download('stopwords', quiet=True)
    nltk.download('wordnet', quiet=True)

download_nltk_data()

# -----------------
# 3. 資料獲取與處理函式
# -----------------
@st.cache_data(ttl=3600)
def fetch_arxiv_data(keyword, max_results):
    """向 arXiv API 發送請求，並解析標題與發表年份。加入 submittedDate 排序以獲取最新趨勢。"""
    url = f'http://export.arxiv.org/api/query?search_query=all:{keyword.replace(" ", "+")}&max_results={max_results}&sortBy=submittedDate&sortOrder=descending'
    
    try:
        response = requests.get(url, timeout=30)
        response.raise_for_status() 
        root = ET.fromstring(response.content)
        
        papers = []
        for entry in root.findall('{http://www.w3.org/2005/Atom}entry'):
            title = entry.find('{http://www.w3.org/2005/Atom}title').text.strip().lower()
            published_str = entry.find('{http://www.w3.org/2005/Atom}published').text
            year = int(published_str[:4])
            papers.append({"title": title, "year": year})
            
        return papers
    except Exception as e:
        st.error(f"API 請求失敗: {e}")
        return []

def process_nlp_ngrams(titles, use_bigrams, use_trigrams, custom_stopwords_str):
    """執行 NLP 文本清理並計算 N-gram 頻率"""
    lemmatizer = WordNetLemmatizer()
    stop_words = set(stopwords.words('english'))
    
    if custom_stopwords_str:
        custom_words = [w.strip().lower() for w in custom_stopwords_str.split(',')]
        stop_words.update(custom_words)

    mixed_ngrams = []
    for title in titles:
        tokens = [word for word in word_tokenize(title) if word.isalpha()]
        lemmatized_tokens = [lemmatizer.lemmatize(word) for word in tokens]

        if use_bigrams:
            for b in nltk.bigrams(lemmatized_tokens):
                if b[0] not in stop_words and b[1] not in stop_words:
                    mixed_ngrams.append(f"{b[0]} {b[1]}")
                    
        if use_trigrams:
            for t in nltk.trigrams(lemmatized_tokens):
                if t[0] not in stop_words and t[2] not in stop_words:
                    mixed_ngrams.append(f"{t[0]} {t[1]} {t[2]}")

    return Counter(mixed_ngrams).most_common(20)

# -----------------
# 4. Streamlit 網頁 UI 佈局
# -----------------
st.title("📊 arXiv 學術論文關鍵字趨勢分析")
st.markdown("透過 arXiv API 抓取最新論文，進行 N-gram 分析與發表趨勢追蹤。")

# === 側邊欄 (Sidebar) ===
st.sidebar.header("⚙️ 分析參數設定")
max_results = st.sidebar.slider("最大檢索篇數 (建議 200-500)", min_value=50, max_value=1000, value=200, step=50)

st.sidebar.subheader("N-gram 設定")
use_bigrams = st.sidebar.checkbox("啟用二元組 (Bigrams)", value=True)
use_trigrams = st.sidebar.checkbox("啟用三元組 (Trigrams)", value=True)

st.sidebar.subheader("NLP 過濾設定")
custom_stopwords_input = st.sidebar.text_area(
    "自訂停用詞 (以逗號分隔)", 
    value="using, research, based, approach, method, via, paper, model, system, data, an, of"
)

# === 主畫面 (Main Content) ===
keyword = st.text_input("🔍 請輸入 arXiv 查詢關鍵字", value="Machine Learning")

if st.button("🚀 開始分析", type="primary"):
    if not keyword.strip():
        st.warning("請輸入有效的關鍵字！")
    elif not use_bigrams and not use_trigrams:
        st.warning("請至少勾選一種 N-gram 類型！")
    else:
        with st.spinner(f"正在擷取 '{keyword}' 的相關論文..."):
            papers = fetch_arxiv_data(keyword, max_results)
        
        if papers:
            st.success(f"✅ 成功獲取 {len(papers)} 篇最新論文！")
            
            # --- 區塊 1: 關鍵字 N-gram 分析 (已移至上方) ---
            st.markdown("---")
            titles = [p["title"] for p in papers]
            with st.spinner("正在進行詞形還原與關鍵字萃取..."):
                most_common_ngrams = process_nlp_ngrams(titles, use_bigrams, use_trigrams, custom_stopwords_input)
            
            if most_common_ngrams:
                df = pd.DataFrame(most_common_ngrams, columns=["Phrase", "Frequency"])
                col1, col2 = st.columns([2, 1])
                
                with col1:
                    st.subheader("📈 Top 20 常見研究片語")
                    chart_df = df.set_index("Phrase").sort_values("Frequency", ascending=True)
                    st.bar_chart(chart_df, horizontal=True)
                    
                with col2:
                    st.subheader("📋 詳細數據清單")
                    st.dataframe(df, use_container_width=True, hide_index=True)
            else:
                st.warning("分析後沒有足夠的有效詞組。")

            # --- 區塊 2: 年份趨勢分析 (已移至下方，並加入年代分組邏輯) ---
            st.markdown("---")
            st.subheader("📅 發文年份趨勢 (按年代分區)")
            
            years = [p["year"] for p in papers]
            year_counts = Counter(years)
            
            if year_counts:
                # 找出所有出現過的年代 (例如: 2010, 2020)
                decades = sorted(list(set((y // 10) * 10 for y in years)))
                
                # 若跨越多個年代，為了版面美觀可使用欄位並排顯示
                cols = st.columns(len(decades))
                
                for idx, decade in enumerate(decades):
                    decade_end = decade + 9
                    
                with cols[idx]:
                    st.markdown(f"**{decade} - {decade_end} 年代**")
                    
                    # 建立該十年的完整年份清單 (確保 X 軸不會缺少某個年份)
                    decade_years = pd.DataFrame({"Year": range(decade, decade_end + 1)})
                    
                    # 擷取屬於該年代的數據
                    decade_data = {y: count for y, count in year_counts.items() if decade <= y <= decade_end}
                    trend_df = pd.DataFrame(decade_data.items(), columns=["Year", "Paper Count"])
                    
                    # 補齊無論文發表的年份，補 0
                    trend_df = pd.merge(decade_years, trend_df, on="Year", how="left").fillna(0)
                    trend_df["Paper Count"] = trend_df["Paper Count"].astype(int)
                    trend_df["Year"] = trend_df["Year"].astype(str)
                    
                    st.line_chart(trend_df.set_index("Year"))
            # --- 區塊 3: 系統自動趨勢分析與建議 ---
            st.markdown("---")
            st.subheader("💡 系統趨勢分析與研究建議")
            
            # 計算論文時間跨度
            min_year, max_year = min(years), max(years)
            year_span = max_year - min_year + 1
            total_papers = len(papers)
            
            # 根據論文時間聚集程度給出動態分析
            if year_span <= 2:
                st.success(
                    f"🔥 **極度熱門領域 (高動能)！**\n\n"
                    f"系統在短短 **{year_span} 年**的時間跨度內，就抓滿了 {total_papers} 篇最新論文，"
                    f"這代表「{keyword}」目前正處於研究**爆發期**，學界投入的資源與關注度極高。\n\n"
                    f"**👉 下手建議：強烈建議切入。** 但由於競爭極其激烈，不建議做純基礎架構的研究。建議往上方「Top 20 常見研究片語」中尋找次級關鍵字，將此熱門技術「跨領域應用」到特定產業或特殊資料集上，尋找利基 (Niche) 切入點。"
                )
            elif year_span <= 6:
                st.info(
                    f"📈 **穩定發展領域 (中動能)**\n\n"
                    f"這 {total_papers} 篇最新論文橫跨了 **{year_span} 年**，顯示該領域並非曇花一現的炒作，而是具有穩定且持續的學術產出。\n\n"
                    f"**👉 下手建議：適合做為長期研究題目。** 此類主題通常已有成熟的衡量標準 (Benchmarks)。建議仔細梳理近 2-3 年的文獻，找出過去方法未能完全解決的痛點 (Pain points) 進行改良。"
                )
            else:
                st.warning(
                    f"🐢 **成熟期或冷門領域 (低動能)**\n\n"
                    f"系統往前追溯了長達 **{year_span} 年**才湊齊 {total_papers} 篇論文，代表此領域近期的發文頻率較低。這通常意味著該技術已經高度成熟（難以發布新突破），或是極度專業的冷門市場。\n\n"
                    f"**👉 下手建議：下手前需謹慎評估。** 除非您的實驗室在此領域有深厚累積，否則不建議單純延續舊方法。建議思考是否能引入近期的破壞性新技術（如深度學習、大型語言模型）來為這個傳統領域帶來降維打擊的突破口。"
                )
                        
                        
        else:
            st.error("無法取得論文資料，請檢查網路或更換關鍵字。")
