import http from "k6/http";
import { check } from "k6";

export const options = {
    vus: 50,
    duration: "20s",
};

const UNSAFE_URL = "http://host.docker.internal/api/v1/events/1/reserve-unsafe";
const SAFE_URL = "http://host.docker.internal/api/v1/events/1/reserve";

export default function () {
    // Dockerコンテナからホスト（Sail）へアクセスするためのURL
    const url = SAFE_URL;
    const payload = JSON.stringify({
        user_id: Math.floor(Math.random() * 1000000),
        // user_id: 1,
    });

    const params = {
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
    };

    const res = http.post(url, payload, params);

    // レスポンスの内容をログに出力
    console.log(`Status: ${res.status}`);
    console.log(`Body: ${res.body}`);
}
