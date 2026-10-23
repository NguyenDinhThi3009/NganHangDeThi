from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "NganHangDeThi RAG Service"
    environment: str = "development"
    qdrant_url: str = "http://127.0.0.1:6333"
    qdrant_api_key: str | None = None
    embedding_model: str = "BAAI/bge-m3"
    llm_provider: str = "openai_compatible"
    llm_base_url: str | None = None
    llm_api_key: str | None = None
    llm_model: str | None = None
    llm_timeout: int = 120
    llm_max_tokens: int = 8192  # gồm cả token "suy nghĩ" của mô hình, đặt thấp dễ bị cắt cụt phản hồi
    chunk_size: int = 1000      # số ký tự tối đa mỗi đoạn
    chunk_overlap: int = 150    # số ký tự chồng lấp giữa hai đoạn liên tiếp

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")


settings = Settings()
