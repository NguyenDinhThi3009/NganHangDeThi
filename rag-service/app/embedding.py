"""Tạo vector bằng mô hình embedding chạy cục bộ (mặc định BAAI/bge-m3)."""
from .config import settings

_model = None


def get_model():
    global _model
    if _model is None:
        from sentence_transformers import SentenceTransformer

        _model = SentenceTransformer(settings.embedding_model)
        _model.max_seq_length = 512
    return _model


def embed_texts(texts: list[str], batch_size: int = 16) -> list[list[float]]:
    vectors = get_model().encode(
        texts, batch_size=batch_size, normalize_embeddings=True, show_progress_bar=False
    )
    return vectors.tolist()


def embedding_dim() -> int:
    return int(get_model().get_sentence_embedding_dimension())
