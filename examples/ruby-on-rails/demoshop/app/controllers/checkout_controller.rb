# coding: utf-8
require "net/http"
require "uri"

class CheckoutController < ApplicationController

  # Вспомогательный метод отправки POST-запроса
  def send_post_request(uri_str, prms)
    uri = URI.parse(uri_str)

    req = Net::HTTP::Post.new(uri.path)
    req.set_form_data(prms)

    https = Net::HTTP.new(uri.host, uri.port)
    https.use_ssl = uri.scheme == "https"

    https.start { |http| http.request(req) }
  end


  def start

    prms = {
        key: params["key"],
        type: params["type"],
        order_id: params["order_id"],
        amount: params["amount"],
        product: params["product"],
    }

    response = send_post_request(params["server"] + "/apim/StartSession", prms)
    if response.kind_of? Net::HTTPSuccess
      jsn = JSON.parse(response.body)
      session_id = jsn['session_id']
      redirect_to params["server"] + "/apim/Pay?session_id=" + session_id
    else
      render json: response.body, status: response.code
    end

    # if response.kind_of? Net::HTTPSuccess
    #   jsn = JSON.parse(response.body)
    #   session_id = jsn['session_id']
    #   if session_id
    #     paysession = Paysession.find_by_string_id(session_id)
    #     if paysession && params[:email]
    #       begin
    #         ServiceMailer.terminal_payment_url(params[:email], paysession).deliver
    #       rescue Exception => e
    #         logger.info "------->>>> ERROR: #{e} ------"
    #       end
    #     elsif paysession && params[:phone]
    #       send_sms(params[:phone], "Добрый день, пройдите по ссылке для оплаты заказа https://secure.payler.com/gapi/Pay?session_id=#{paysession.string_id}")
    #     end
    #     return render json: {message: "Ссылка на оплату заказа № #{paysession.merchant_order_id} успешно отправлена клиенту."}
    #   end
    # end
  end
end
