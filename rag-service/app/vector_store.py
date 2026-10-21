from qdrant_client import QdrantClient
from .config import settings

CHUNK_COLLECTION = "chunk_tai_lieu"
QUESTION_COLLECTION = "cau_hoi"


def get_qdrant_client() -> QdrantClient:
    return QdrantClient(
        url=settings.qdrant_url,
        api_key=settings.qdrant_api_key or None,
    )


def ensure_collections(vector_size: int = 1024) -> None:
    """Create starter collections if missing. BGE-M3 dense vectors commonly use 1024 dimensions."""
    from qdrant_client.models import Distance, VectorParams

    client = get_qdrant_client()
    existing = {item.name for item in client.get_collections().collections}
    for name in (CHUNK_COLLECTION, QUESTION_COLLECTION):
        if name not in existing:
            client.create_collection(
                collection_name=name,
                vectors_config=VectorParams(size=vector_size, distance=Distance.COSINE),
            )
