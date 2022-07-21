---
layout: page
title: About
description: 打码改变世界
keywords: Zhuang Ma, 马壮
comments: true
menu: 关于
permalink: /about/
---

## about me

- 一个互联网接入层相关开发者，专注于网络协议优化，网络协议安全，接入层架构设计，以及高性能服务器开发等技术方向。
- 热衷于IETF标准化建设，参与了[RFC8998](https://datatracker.ietf.org/doc/html/rfc8998), [IETF-QUIC-LB](https://datatracker.ietf.org/doc/html/draft-ietf-quic-load-balancers)等技术的标准化，正在以作者身份标准化[TURN-LB](http://www.watersprings.org/pub/id/draft-zeng-turn-cluster-03.html)技术
- 热衷于开源，是[BabaSSL](https://github.com/BabaSSL/BabaSSL)项目的commiter, [QUIC-LB](https://github.com/alipay/quic-lb)项目的作者。

如果您对相关技术及项目感兴趣或者有相关问题想咨询，欢迎您通过邮箱和电话等方式联系我。

## Contact Me

<ul>
{% for website in site.data.social %}
<li>{{website.sitename }}：<a href="{{ website.url }}" target="_blank">@{{ website.name }}</a></li>
{% endfor %}
</ul>
